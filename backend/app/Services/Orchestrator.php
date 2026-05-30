<?php

namespace App\Services;

use App\Events\CouncilSessionUpdated;
use App\Models\Advisor;
use App\Models\AdvisorResponse;
use App\Models\BoardSession;
use App\Support\SessionBroadcastPayload;
use App\Support\SessionPresenter;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Queue\MaxAttemptsExceededException;
use Illuminate\Queue\TimeoutExceededException;
use Psr\Http\Message\ResponseInterface;

class Orchestrator
{
    public function __construct(private readonly OpenRouterClient $openai) {}

    public function prepareSession(BoardSession $session, Collection $advisors): BoardSession
    {
        $session->update([
            'status' => 'processing',
            'consensus' => null,
            'failure_reason' => null,
            'advisor_failures' => [],
            'active_advisor_ids' => [],
            'selected_tensions' => [],
        ]);

        Log::info('[Council] Deliberation started', [
            'session_id' => $session->id,
            'advisor_count' => $advisors->count(),
        ]);

        $freshSession = $session->fresh();

        $this->broadcastUpdate($freshSession, [
            'phase' => 'started',
            'current_round' => 1,
            'completed_advisors' => 0,
            'failed_advisors' => 0,
            'total_advisors' => $advisors->count(),
        ]);

        return $freshSession;
    }

    public function handleIndependentAdvisor(BoardSession $session, Advisor $advisor): void
    {
        $this->handleAdvisorResponse(
            $session,
            $advisor,
            'independent',
            1,
            null,
            [
                ['role' => 'system', 'content' => $advisor->system_prompt],
                ['role' => 'user', 'content' => $session->question],
            ],
            'advisor_started',
            'advisor_completed'
        );
    }

    public function selectCritiqueTensions(BoardSession $session): array
    {
        $session = $session->fresh()->load('advisorResponses.advisor');

        $independentOutputs = $session->advisorResponses
            ->filter(fn ($response) => $response->response_type === 'independent')
            ->map(fn ($response) => [
                'name' => $response->advisor?->name ?? 'Advisor',
                'role' => $response->advisor?->role ?? 'advisor',
                'content' => $response->content,
            ])
            ->values()
            ->all();

        if ($independentOutputs === []) {
            return [];
        }

        $chair = Advisor::where('role', 'chair')
            ->where('active', true)
            ->firstOrFail();

        Log::info('[Council] Selecting critique tensions', [
            'session_id' => $session->id,
            'model' => $chair->model,
        ]);

        $this->broadcastUpdate($session, [
            'phase' => 'tensions_started',
            'current_round' => 2,
        ]);

        try {
            $result = $this->openai->createChat([
                'model' => $chair->model,
                'messages' => [
                    ['role' => 'system', 'content' => $chair->system_prompt],
                    ['role' => 'user', 'content' => $this->buildTensionSelectionPrompt($session->question, $independentOutputs, $session->advisor_failures ?? [])],
                ],
            ]);

            $tensions = $this->extractTensions($result->choices[0]->message->content, $independentOutputs, $session->advisor_failures ?? []);
        } catch (\Throwable $e) {
            Log::error('[Council] Tension selection failed; using fallback tensions', [
                'session_id' => $session->id,
                ...$this->buildExceptionContext($e),
            ]);

            $tensions = $this->fallbackTensions($independentOutputs, $session->advisor_failures ?? []);
        }

        $session->update([
            'selected_tensions' => $tensions,
        ]);

        $updatedSession = $session->fresh();

        $this->broadcastUpdate($updatedSession, [
            'phase' => 'tensions_selected',
            'current_round' => 2,
            'tension_count' => count($tensions),
        ]);

        return $tensions;
    }

    public function handleCritiqueAdvisor(BoardSession $session, Advisor $advisor, array $tension): void
    {
        $session = $session->fresh()->load('advisorResponses.advisor');

        $this->handleAdvisorResponse(
            $session,
            $advisor,
            'critique',
            2,
            $tension,
            [
                ['role' => 'system', 'content' => $advisor->system_prompt],
                ['role' => 'user', 'content' => $this->buildCritiquePrompt($session, $advisor, $tension)],
            ],
            'critique_started',
            'critique_completed'
        );
    }

    public function finalize(BoardSession $session): BoardSession
    {
        $session = $session->fresh()->load('advisorResponses.advisor');

        $chair = Advisor::where('role', 'chair')
            ->where('active', true)
            ->firstOrFail();

        $advisorOutputs = $session->advisorResponses
            ->filter(fn ($response) => $response->advisor?->role !== 'chair' && $response->response_type === 'independent')
            ->map(fn ($response) => [
                'name' => $response->advisor?->name ?? 'Advisor',
                'role' => $response->advisor?->role ?? 'advisor',
                'content' => $response->content,
            ])
            ->values()
            ->all();

        $critiqueOutputs = $session->advisorResponses
            ->filter(fn ($response) => $response->advisor?->role !== 'chair' && $response->response_type === 'critique')
            ->map(fn ($response) => [
                'name' => $response->advisor?->name ?? 'Advisor',
                'role' => $response->advisor?->role ?? 'advisor',
                'content' => $response->content,
                'tension_label' => $response->tension_label,
                'tension_key' => $response->tension_key,
            ])
            ->values()
            ->all();

        if ($advisorOutputs === []) {
            $reason = 'All advisor calls failed. See advisor_failures for exact reasons.';

            Log::error('Orchestrator deliberation failed', [
                'session_id' => $session->id,
                'message' => $reason,
                'advisor_failures' => $session->advisor_failures ?? [],
            ]);

            $session->update([
                'status' => 'failed',
                'failure_reason' => $reason,
                'active_advisor_ids' => [],
            ]);

            $failedSession = $session->fresh();

            $this->broadcastUpdate($failedSession, [
                'phase' => 'failed',
                'error' => $reason,
            ]);

            return $failedSession;
        }

        Log::info('[Council] Calling Chair for synthesis', [
            'session_id' => $session->id,
            'model' => $chair->model,
            'successful_advisors' => count($advisorOutputs),
            'successful_critiques' => count($critiqueOutputs),
            'failed_advisors' => count($session->advisor_failures ?? []),
        ]);

        $this->broadcastUpdate($session, [
            'phase' => 'chair_started',
            'active_advisor' => $this->advisorSummary($chair),
        ]);

        try {
            $chairResult = $this->openai->createChat([
                'model' => $chair->model,
                'messages' => [
                    ['role' => 'system', 'content' => $chair->system_prompt],
                    ['role' => 'user', 'content' => $this->buildSynthesisPrompt($session, $advisorOutputs, $critiqueOutputs, $session->advisor_failures ?? [])],
                ],
            ]);
        } catch (\Throwable $e) {
            $reason = $this->buildUserFacingError($e);

            Log::error('[Council] Chair synthesis failed', [
                'session_id' => $session->id,
                'advisor' => $chair->name,
                'model' => $chair->model,
                ...$this->buildExceptionContext($e),
            ]);

            $session->update([
                'status' => 'failed',
                'failure_reason' => $reason,
                'active_advisor_ids' => [],
            ]);

            $failedSession = $session->fresh();

            $this->broadcastUpdate($failedSession, [
                'phase' => 'failed',
                'error' => $reason,
            ]);

            throw $e;
        }

        $chairData = $chairResult->toArray();
        $consensus = $chairResult->choices[0]->message->content;
        $chairUsage = $chairResult->usage;

        AdvisorResponse::updateOrCreate(
            [
                'board_session_id' => $session->id,
                'advisor_id' => $chair->id,
                'response_type' => 'chair_summary',
                'round_number' => $session->deliberation_mode === 'two_round' ? 3 : 2,
                'tension_key' => null,
            ],
            [
                'tension_label' => null,
                'content' => $consensus,
                'model_used' => $chair->model,
                'prompt_tokens' => $chairUsage->promptTokens ?? 0,
                'completion_tokens' => $chairUsage->completionTokens ?? 0,
                'cost_gbp' => $chairData['usage']['total_cost_gbp'] ?? 0,
            ]
        );

        $session->update([
            'status' => 'complete',
            'consensus' => $consensus,
            'failure_reason' => null,
            'active_advisor_ids' => [],
        ]);

        Log::info('[Council] Deliberation complete', ['session_id' => $session->id]);

        $completedSession = $session->fresh();

        $this->broadcastUpdate($completedSession, [
            'phase' => 'completed',
        ]);

        return $completedSession->load('advisorResponses.advisor');
    }

    private function buildSynthesisPrompt(BoardSession $session, array $advisorOutputs, array $critiqueOutputs, array $advisorFailures = []): string
    {
        $question = $session->question;
        $parts = ["The question put to the council was:\n\n{$question}\n\nThe council's independent responses were as follows:"];

        foreach ($advisorOutputs as $output) {
            $parts[] = "**{$output['name']}** ({$output['role']}):\n\n{$output['content']}";
        }

        if ($session->deliberation_mode === 'two_round') {
            $parts[] = 'The orchestrator selected the following tensions for critique:';

            foreach ($session->selected_tensions ?? [] as $tension) {
                $parts[] = "**{$tension['label']}**: {$tension['question']}";
            }

            if ($critiqueOutputs !== []) {
                $parts[] = 'The council then produced targeted critiques:';

                foreach ($critiqueOutputs as $critique) {
                    $label = $critique['tension_label'] ? " [{$critique['tension_label']}]" : '';
                    $parts[] = "**{$critique['name']}** ({$critique['role']}){$label}:\n\n{$critique['content']}";
                }
            }
        }

        if ($advisorFailures !== []) {
            $parts[] = 'The following advisors failed to respond. Ignore their missing responses, but note the omissions if they materially limit confidence:';

            foreach ($advisorFailures as $failure) {
                $context = $failure['response_type'] === 'critique'
                    ? "during critique round for {$failure['tension_label']}"
                    : 'during independent response round';

                $parts[] = "**{$failure['name']}** ({$failure['role']}) failed {$context}: {$failure['message']}";
            }
        }

        $parts[] = 'Synthesise the council\'s perspectives into a coherent overall assessment. If critiques are present, integrate them into the final reasoning rather than merely repeating Round 1.';

        return implode("\n\n---\n\n", $parts);
    }

    private function handleAdvisorResponse(
        BoardSession $session,
        Advisor $advisor,
        string $responseType,
        int $roundNumber,
        ?array $tension,
        array $messages,
        string $startedPhase,
        string $completedPhase,
    ): void {
        $updatedSession = $this->markAdvisorStarted($session, $advisor);

        Log::info('[Council] Calling advisor', [
            'session_id' => $session->id,
            'advisor' => $advisor->name,
            'model' => $advisor->model,
            'response_type' => $responseType,
            'round_number' => $roundNumber,
            'tension_key' => $tension['key'] ?? null,
        ]);

        try {
            $result = $this->openai->createChat([
                'model' => $advisor->model,
                'messages' => $messages,
            ]);

            $data = $result->toArray();

            if (empty($data['choices'])) {
                Log::error('[Council] Unexpected response from advisor', [
                    'session_id' => $session->id,
                    'advisor' => $advisor->name,
                    'model' => $advisor->model,
                    'response' => $data,
                ]);

                throw new \RuntimeException("No choices in response from {$advisor->name} ({$advisor->model})");
            }

            $content = $result->choices[0]->message->content;
            $usage = $result->usage;

            AdvisorResponse::updateOrCreate(
                [
                    'board_session_id' => $session->id,
                    'advisor_id' => $advisor->id,
                    'response_type' => $responseType,
                    'round_number' => $roundNumber,
                    'tension_key' => $tension['key'] ?? null,
                ],
                [
                    'tension_label' => $tension['label'] ?? null,
                    'content' => $content,
                    'model_used' => $advisor->model,
                    'prompt_tokens' => $usage->promptTokens ?? 0,
                    'completion_tokens' => $usage->completionTokens ?? 0,
                    'cost_gbp' => $data['usage']['total_cost_gbp'] ?? 0,
                ]
            );

            Log::info('[Council] Advisor responded', [
                'session_id' => $session->id,
                'advisor' => $advisor->name,
                'response_type' => $responseType,
                'round_number' => $roundNumber,
                'prompt_tokens' => $usage->promptTokens ?? 0,
                'completion_tokens' => $usage->completionTokens ?? 0,
            ]);

            $updatedSession = $this->markAdvisorCompleted($updatedSession, $advisor);

            $this->broadcastUpdate($updatedSession, [
                'phase' => $completedPhase,
                'current_round' => $roundNumber,
                'active_advisor' => $this->advisorSummary($advisor),
                'tension_key' => $tension['key'] ?? null,
            ]);
        } catch (\Throwable $e) {
            $this->recordAdvisorFailure($updatedSession, $advisor, $e, $responseType, $roundNumber, $tension);

            throw $e;
        }
    }

    private function buildTensionSelectionPrompt(string $question, array $advisorOutputs, array $advisorFailures): string
    {
        $parts = [
            'You are selecting targeted critique tensions for a council of advisors.',
            'Return strict JSON with a top-level key "tensions" containing 1 or 2 tensions.',
            'Each tension must contain: key, label, question, advisors_involved, why_it_matters.',
            'Choose only the most important substantive disagreements, omissions, or uncertainty clusters.',
            'Question under discussion:',
            $question,
            'Independent responses:',
        ];

        foreach ($advisorOutputs as $output) {
            $parts[] = "- {$output['name']} ({$output['role']}): {$output['content']}";
        }

        if ($advisorFailures !== []) {
            $parts[] = 'Failed advisors:';

            foreach ($advisorFailures as $failure) {
                $parts[] = "- {$failure['name']} ({$failure['role']}): {$failure['message']}";
            }
        }

        return implode("\n\n", $parts);
    }

    private function extractTensions(string $content, array $advisorOutputs, array $advisorFailures): array
    {
        $decoded = $this->decodeJsonPayload($content);
        $tensions = data_get($decoded, 'tensions');

        if (! is_array($tensions) || $tensions === []) {
            return $this->fallbackTensions($advisorOutputs, $advisorFailures);
        }

        return collect($tensions)
            ->filter(fn ($tension) => is_array($tension) && ! empty($tension['question']))
            ->take(2)
            ->values()
            ->map(function (array $tension, int $index) {
                return [
                    'key' => (string) ($tension['key'] ?? 'tension-'.($index + 1)),
                    'label' => (string) ($tension['label'] ?? 'Selected Tension '.($index + 1)),
                    'question' => (string) $tension['question'],
                    'advisors_involved' => array_values(array_map('strval', $tension['advisors_involved'] ?? [])),
                    'why_it_matters' => (string) ($tension['why_it_matters'] ?? ''),
                ];
            })
            ->all();
    }

    private function fallbackTensions(array $advisorOutputs, array $advisorFailures): array
    {
        if (count($advisorOutputs) === 1) {
            return [[
                'key' => 'tension-1',
                'label' => 'Challenge The Sole Recommendation',
                'question' => 'What is the strongest objection, omitted constraint, or uncertainty that could weaken the council\'s current leading recommendation?',
                'advisors_involved' => [$advisorOutputs[0]['role']],
                'why_it_matters' => 'A single unchallenged recommendation needs adversarial testing before synthesis.',
            ]];
        }

        $tensions = [[
            'key' => 'tension-1',
            'label' => 'Core Recommendation Conflict',
            'question' => 'Where do the advisors most substantively disagree on the recommended course of action, and which trade-off is genuinely decision-critical?',
            'advisors_involved' => array_values(array_unique(array_map(fn ($output) => $output['role'], $advisorOutputs))),
            'why_it_matters' => 'The final recommendation depends on resolving the biggest conflict in the round-one advice.',
        ]];

        if (count($advisorOutputs) > 2 || $advisorFailures !== []) {
            $tensions[] = [
                'key' => 'tension-2',
                'label' => 'Missing Risks And Assumptions',
                'question' => 'Which hidden assumptions, missing constraints, or omitted risks in Round 1 are most likely to distort the final recommendation?',
                'advisors_involved' => array_values(array_unique(array_map(fn ($output) => $output['role'], $advisorOutputs))),
                'why_it_matters' => 'The final synthesis should not ignore weak assumptions or evidence gaps.',
            ];
        }

        return $tensions;
    }

    private function decodeJsonPayload(string $content): ?array
    {
        $trimmed = trim($content);

        if (str_starts_with($trimmed, '```')) {
            $trimmed = preg_replace('/^```(?:json)?\s*/', '', $trimmed) ?? $trimmed;
            $trimmed = preg_replace('/\s*```$/', '', $trimmed) ?? $trimmed;
        }

        $decoded = json_decode($trimmed, true);

        if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
            return $decoded;
        }

        if (preg_match('/\{.*\}/s', $trimmed, $matches) === 1) {
            $decoded = json_decode($matches[0], true);

            if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
                return $decoded;
            }
        }

        return null;
    }

    private function buildCritiquePrompt(BoardSession $session, Advisor $advisor, array $tension): string
    {
        $independentOutputs = $session->advisorResponses
            ->filter(fn ($response) => $response->response_type === 'independent')
            ->map(fn ($response) => [
                'name' => $response->advisor?->name ?? 'Advisor',
                'role' => $response->advisor?->role ?? 'advisor',
                'content' => $response->content,
            ])
            ->values()
            ->all();

        $parts = [
            "The council question is:\n\n{$session->question}",
            "You are now performing a targeted critique on this orchestrator-selected tension:\n\n{$tension['label']}: {$tension['question']}",
            'Round 1 independent responses:',
        ];

        foreach ($independentOutputs as $output) {
            $parts[] = "**{$output['name']}** ({$output['role']}):\n\n{$output['content']}";
        }

        $parts[] = 'Critique this specific tension. Do not restate your original answer. Focus on contradictions, weak assumptions, omitted constraints, uncertainty, and which side of the tension is better supported.';

        return implode("\n\n---\n\n", $parts);
    }

    private function markAdvisorStarted(BoardSession $session, Advisor $advisor): BoardSession
    {
        $updatedSession = $this->withLockedSession($session->id, function (BoardSession $lockedSession) use ($advisor) {
            $activeAdvisorIds = collect($lockedSession->active_advisor_ids ?? [])
                ->push($advisor->id)
                ->unique()
                ->values()
                ->all();

            $lockedSession->update([
                'active_advisor_ids' => $activeAdvisorIds,
            ]);
        });

        $this->broadcastUpdate($updatedSession, [
            'phase' => 'advisor_started',
            'active_advisor' => $this->advisorSummary($advisor),
        ]);

        return $updatedSession;
    }

    private function markAdvisorCompleted(BoardSession $session, Advisor $advisor): BoardSession
    {
        return $this->withLockedSession($session->id, function (BoardSession $lockedSession) use ($advisor) {
            $activeAdvisorIds = collect($lockedSession->active_advisor_ids ?? [])
                ->reject(fn ($advisorId) => $advisorId === $advisor->id)
                ->values()
                ->all();

            $lockedSession->update([
                'active_advisor_ids' => $activeAdvisorIds,
            ]);
        });
    }

    private function recordAdvisorFailure(BoardSession $session, Advisor $advisor, \Throwable $e, string $responseType = 'independent', int $roundNumber = 1, ?array $tension = null): BoardSession
    {
        $reason = $this->buildUserFacingError($e);

        Log::error('[Council] Advisor call failed', [
            'session_id' => $session->id,
            'advisor' => $advisor->name,
            'model' => $advisor->model,
            ...$this->buildExceptionContext($e),
        ]);

        $failure = [
            'advisor_id' => $advisor->id,
            'name' => $advisor->name,
            'role' => $advisor->role,
            'model' => $advisor->model,
            'response_type' => $responseType,
            'round_number' => $roundNumber,
            'tension_key' => $tension['key'] ?? null,
            'tension_label' => $tension['label'] ?? null,
            'message' => $reason,
        ];

        $updatedSession = $this->withLockedSession($session->id, function (BoardSession $lockedSession) use ($advisor, $failure) {
            $activeAdvisorIds = collect($lockedSession->active_advisor_ids ?? [])
                ->reject(fn ($advisorId) => $advisorId === $advisor->id)
                ->values()
                ->all();

            $failures = collect($lockedSession->advisor_failures ?? [])
                ->reject(fn ($entry) => ($entry['advisor_id'] ?? null) === $advisor->id)
                ->push($failure)
                ->values()
                ->all();

            $lockedSession->update([
                'active_advisor_ids' => $activeAdvisorIds,
                'advisor_failures' => $failures,
            ]);
        });

        $this->broadcastUpdate($updatedSession, [
            'phase' => $responseType === 'critique' ? 'critique_failed' : 'advisor_failed',
            'current_round' => $roundNumber,
            'error' => $reason,
            'failed_advisor' => $failure,
        ]);

        return $updatedSession;
    }

    private function withLockedSession(int $sessionId, callable $callback): BoardSession
    {
        return DB::transaction(function () use ($sessionId, $callback) {
            $lockedSession = BoardSession::query()->lockForUpdate()->findOrFail($sessionId);

            $callback($lockedSession);

            return $lockedSession->fresh();
        });
    }

    private function buildUserFacingError(\Throwable $e): string
    {
        if ($e instanceof TimeoutExceededException) {
            return 'The advisor timed out while waiting for the model provider.';
        }

        if ($e instanceof MaxAttemptsExceededException) {
            return 'The advisor job exceeded its queue attempt limit after a previous failure.';
        }

        $context = $this->buildExceptionContext($e);

        $raw = data_get($context, 'response_json.error.metadata.raw');

        if (is_string($raw)) {
            $decodedRaw = json_decode($raw, true);

            if (json_last_error() === JSON_ERROR_NONE && is_array($decodedRaw) && ! empty($decodedRaw['message'])) {
                return (string) $decodedRaw['message'];
            }
        }

        $providerMessage = data_get($context, 'response_json.error.message');

        if (is_string($providerMessage) && $providerMessage !== '') {
            return $this->sanitizeFailureMessage($providerMessage);
        }

        return $this->sanitizeFailureMessage($e->getMessage());
    }

    private function sanitizeFailureMessage(string $message): string
    {
        $normalized = preg_replace('/\s+/', ' ', trim($message)) ?? trim($message);

        if (str_contains($normalized, 'duplicate key value violates unique constraint "failed_jobs_uuid_unique"')) {
            return 'The advisor job timed out and Laravel attempted to record the same failed job twice.';
        }

        return mb_substr($normalized, 0, 280);
    }

    private function advisorSummary(Advisor $advisor): array
    {
        return [
            'id' => $advisor->id,
            'name' => $advisor->name,
            'role' => $advisor->role,
            'model' => $advisor->model,
        ];
    }

    private function buildProgress(BoardSession $session, array $overrides = []): array
    {
        $completedAdvisors = AdvisorResponse::query()
            ->where('board_session_id', $session->id)
            ->where('response_type', '!=', 'chair_summary')
            ->whereHas('advisor', fn ($query) => $query->where('role', '!=', 'chair'))
            ->count();

        $failedAdvisors = count($session->advisor_failures ?? []);
        $totalAdvisors = Advisor::where('active', true)
            ->where('role', '!=', 'chair')
            ->count() * ($session->deliberation_mode === 'two_round' ? 2 : 1);

        $activeAdvisorIds = $session->active_advisor_ids ?? [];
        $activeAdvisors = Advisor::whereIn('id', $activeAdvisorIds)
            ->get()
            ->keyBy('id');

        return array_merge([
            'completed_advisors' => $completedAdvisors,
            'failed_advisors' => $failedAdvisors,
            'total_advisors' => $totalAdvisors,
            'active_advisors' => collect($activeAdvisorIds)
                ->map(fn ($advisorId) => $activeAdvisors->get($advisorId))
                ->filter()
                ->map(fn ($advisor) => $this->advisorSummary($advisor))
                ->values()
                ->all(),
        ], $overrides);
    }

    private function buildExceptionContext(\Throwable $e): array
    {
        $context = [
            'exception' => $e::class,
            'message'   => $e->getMessage(),
            'code'      => $e->getCode(),
        ];

        if (method_exists($e, 'getStatusCode')) {
            $context['status_code'] = $e->getStatusCode();
        }

        if (method_exists($e, 'getErrorType')) {
            $context['error_type'] = $e->getErrorType();
        }

        if (method_exists($e, 'getErrorCode')) {
            $context['error_code'] = $e->getErrorCode();
        }

        if ($response = $this->extractResponse($e)) {
            $body = (string) $response->getBody();

            $context['response_status'] = $response->getStatusCode();
            $context['response_content_type'] = $response->getHeaderLine('Content-Type');
            $context['response_body'] = mb_substr($body, 0, 4000);

            $decoded = json_decode($body, true);

            if (json_last_error() === JSON_ERROR_NONE) {
                $context['response_json'] = $decoded;
            }
        }

        if ($e->getPrevious()) {
            $context['previous_exception'] = $e->getPrevious()::class;
            $context['previous_message'] = $e->getPrevious()?->getMessage();
        }

        return $context;
    }

    private function extractResponse(\Throwable $e): ?ResponseInterface
    {
        if (property_exists($e, 'response') && $e->response instanceof ResponseInterface) {
            return $e->response;
        }

        $previous = $e->getPrevious();

        if ($previous instanceof \Throwable) {
            return $this->extractResponse($previous);
        }

        return null;
    }

    private function broadcastUpdate(BoardSession $session, array $progress): void
    {
        event(new CouncilSessionUpdated(
            $session->id,
            SessionBroadcastPayload::fromSession($session->fresh(), $this->buildProgress($session->fresh(), $progress)),
        ));
    }
}

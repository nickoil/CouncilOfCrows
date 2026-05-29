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
        ]);

        Log::info('[Council] Deliberation started', [
            'session_id' => $session->id,
            'advisor_count' => $advisors->count(),
        ]);

        $freshSession = $session->fresh();

        $this->broadcastUpdate($freshSession, [
            'phase' => 'started',
            'completed_advisors' => 0,
            'failed_advisors' => 0,
            'total_advisors' => $advisors->count(),
        ]);

        return $freshSession;
    }

    public function handleAdvisor(BoardSession $session, Advisor $advisor): void
    {
        $updatedSession = $this->markAdvisorStarted($session, $advisor);

        Log::info('[Council] Calling advisor', [
            'session_id' => $session->id,
            'advisor' => $advisor->name,
            'model' => $advisor->model,
        ]);

        try {
            $result = $this->openai->createChat([
                'model' => $advisor->model,
                'messages' => [
                    ['role' => 'system', 'content' => $advisor->system_prompt],
                    ['role' => 'user', 'content' => $session->question],
                ],
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
                ],
                [
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
                'prompt_tokens' => $usage->promptTokens ?? 0,
                'completion_tokens' => $usage->completionTokens ?? 0,
            ]);

            $updatedSession = $this->markAdvisorCompleted($updatedSession, $advisor);

            $this->broadcastUpdate($updatedSession, [
                'phase' => 'advisor_completed',
                'active_advisor' => $this->advisorSummary($advisor),
            ]);
        } catch (\Throwable $e) {
            $updatedSession = $this->recordAdvisorFailure($updatedSession, $advisor, $e);

            throw $e;
        }
    }

    public function finalize(BoardSession $session): BoardSession
    {
        $session = $session->fresh()->load('advisorResponses.advisor');

        $chair = Advisor::where('role', 'chair')
            ->where('active', true)
            ->firstOrFail();

        $advisorOutputs = $session->advisorResponses
            ->filter(fn ($response) => $response->advisor?->role !== 'chair')
            ->map(fn ($response) => [
                'name' => $response->advisor?->name ?? 'Advisor',
                'role' => $response->advisor?->role ?? 'advisor',
                'content' => $response->content,
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
                    ['role' => 'user', 'content' => $this->buildSynthesisPrompt($session->question, $advisorOutputs, $session->advisor_failures ?? [])],
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
            ],
            [
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

    private function buildSynthesisPrompt(string $question, array $advisorOutputs, array $advisorFailures = []): string
    {
        $parts = ["The question put to the council was:\n\n{$question}\n\nThe council's independent responses were as follows:"];

        foreach ($advisorOutputs as $output) {
            $parts[] = "**{$output['name']}** ({$output['role']}):\n\n{$output['content']}";
        }

        if ($advisorFailures !== []) {
            $parts[] = 'The following advisors failed to respond. Ignore their missing responses, but note the omissions if they materially limit confidence:';

            foreach ($advisorFailures as $failure) {
                $parts[] = "**{$failure['name']}** ({$failure['role']}): {$failure['message']}";
            }
        }

        $parts[] = "Synthesise the council's perspectives into a coherent overall assessment.";

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

    private function recordAdvisorFailure(BoardSession $session, Advisor $advisor, \Throwable $e): BoardSession
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
            'phase' => 'advisor_failed',
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
            return $providerMessage;
        }

        return $e->getMessage();
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
            ->whereHas('advisor', fn ($query) => $query->where('role', '!=', 'chair'))
            ->count();

        $failedAdvisors = count($session->advisor_failures ?? []);
        $totalAdvisors = Advisor::where('active', true)
            ->where('role', '!=', 'chair')
            ->count();

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

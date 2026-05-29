<?php

namespace App\Services;

use App\Models\Advisor;
use App\Models\AdvisorResponse;
use App\Models\BoardSession;
use Illuminate\Support\Facades\Log;
use OpenAI\Client as OpenAIClient;
use Psr\Http\Message\ResponseInterface;

class Orchestrator
{
    public function __construct(private readonly OpenAIClient $openai) {}

    public function deliberate(string $question): BoardSession
    {
        $session = BoardSession::create(['question' => $question, 'status' => 'processing']);

        try {
            $advisors = Advisor::where('active', true)
                ->where('role', '!=', 'chair')
                ->orderBy('id')
                ->get();

            $chair = Advisor::where('role', 'chair')
                ->where('active', true)
                ->firstOrFail();

            $advisorOutputs = [];

            Log::info('[Council] Deliberation started', [
                'session_id'    => $session->id,
                'advisor_count' => $advisors->count(),
            ]);

            foreach ($advisors as $index => $advisor) {
                Log::info('[Council] Calling advisor', [
                    'session_id' => $session->id,
                    'step'       => ($index + 1).'/'.$advisors->count(),
                    'advisor'    => $advisor->name,
                    'model'      => $advisor->model,
                ]);

                try {
                    $result = $this->openai->chat()->create([
                        'model'    => $advisor->model,
                        'messages' => [
                            ['role' => 'system', 'content' => $advisor->system_prompt],
                            ['role' => 'user',   'content' => $question],
                        ],
                    ]);
                } catch (\Throwable $e) {
                    Log::error('[Council] Advisor call failed', [
                        'session_id' => $session->id,
                        'step'       => ($index + 1).'/'.$advisors->count(),
                        'advisor'    => $advisor->name,
                        'model'      => $advisor->model,
                        ...$this->buildExceptionContext($e),
                    ]);

                    throw $e;
                }

                $data = $result->toArray();

                if (empty($data['choices'])) {
                    Log::error('[Council] Unexpected response from advisor', [
                        'session_id' => $session->id,
                        'advisor'    => $advisor->name,
                        'model'      => $advisor->model,
                        'response'   => $data,
                    ]);
                    throw new \RuntimeException("No choices in response from {$advisor->name} ({$advisor->model})");
                }

                $content = $result->choices[0]->message->content;
                $usage   = $result->usage;

                Log::info('[Council] Advisor responded', [
                    'session_id'        => $session->id,
                    'advisor'           => $advisor->name,
                    'prompt_tokens'     => $usage->promptTokens ?? 0,
                    'completion_tokens' => $usage->completionTokens ?? 0,
                ]);

                AdvisorResponse::create([
                    'board_session_id'  => $session->id,
                    'advisor_id'        => $advisor->id,
                    'content'           => $content,
                    'model_used'        => $advisor->model,
                    'prompt_tokens'     => $usage->promptTokens ?? 0,
                    'completion_tokens' => $usage->completionTokens ?? 0,
                    'cost_gbp'          => $data['usage']['total_cost_gbp'] ?? 0,
                ]);

                $advisorOutputs[] = [
                    'name'    => $advisor->name,
                    'role'    => $advisor->role,
                    'content' => $content,
                ];
            }

            // Chair synthesises all responses
            Log::info('[Council] Calling Chair for synthesis', [
                'session_id' => $session->id,
                'model'      => $chair->model,
            ]);

            try {
                $chairResult = $this->openai->chat()->create([
                    'model'    => $chair->model,
                    'messages' => [
                        ['role' => 'system', 'content' => $chair->system_prompt],
                        ['role' => 'user',   'content' => $this->buildSynthesisPrompt($question, $advisorOutputs)],
                    ],
                ]);
            } catch (\Throwable $e) {
                Log::error('[Council] Chair synthesis failed', [
                    'session_id' => $session->id,
                    'advisor'    => $chair->name,
                    'model'      => $chair->model,
                    ...$this->buildExceptionContext($e),
                ]);

                throw $e;
            }

            $chairData  = $chairResult->toArray();
            $consensus  = $chairResult->choices[0]->message->content;
            $chairUsage = $chairResult->usage;

            AdvisorResponse::create([
                'board_session_id'  => $session->id,
                'advisor_id'        => $chair->id,
                'content'           => $consensus,
                'model_used'        => $chair->model,
                'prompt_tokens'     => $chairUsage->promptTokens ?? 0,
                'completion_tokens' => $chairUsage->completionTokens ?? 0,
                'cost_gbp'          => $chairData['usage']['total_cost_gbp'] ?? 0,
            ]);

            $session->update(['status' => 'complete', 'consensus' => $consensus]);

            Log::info('[Council] Deliberation complete', ['session_id' => $session->id]);

        } catch (\Throwable $e) {
            Log::error('Orchestrator deliberation failed', [
                'session_id' => $session->id,
                ...$this->buildExceptionContext($e),
            ]);
            $session->update(['status' => 'failed']);
            throw $e;
        }

        return $session->load('advisorResponses.advisor');
    }

    private function buildSynthesisPrompt(string $question, array $advisorOutputs): string
    {
        $parts = ["The question put to the council was:\n\n{$question}\n\nThe council's independent responses were as follows:"];

        foreach ($advisorOutputs as $output) {
            $parts[] = "**{$output['name']}** ({$output['role']}):\n\n{$output['content']}";
        }

        $parts[] = "Synthesise the council's perspectives into a coherent overall assessment.";

        return implode("\n\n---\n\n", $parts);
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
}

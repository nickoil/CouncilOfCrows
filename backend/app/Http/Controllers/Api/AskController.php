<?php
namespace App\Http\Controllers\Api;
use App\Http\Controllers\Controller;
use App\Models\AdvisorResponse;
use App\Models\BoardSession;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use OpenAI\Client as OpenAIClient;

class AskController extends Controller
{
    public function __construct(private readonly OpenAIClient $openai) {}

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'question' => 'required|string|max:2000',
        ]);

        $session = BoardSession::create([
            'question' => $validated['question'],
            'status'   => 'processing',
        ]);

        try {
            $data  = 'na';
            $model = config('openrouter.default_model');

            $result = $this->openai->chat()->create([
                'model'    => $model,
                'messages' => [
                    ['role' => 'system', 'content' => 'You are a helpful, direct advisor. Answer clearly and concisely.'],
                    ['role' => 'user',   'content' => $validated['question']],
                ],
            ]);

            $data    = $result->toArray();
            $content = $result->choices[0]->message->content;
            $usage   = $result->usage;

            AdvisorResponse::create([
                'board_session_id'  => $session->id,
                'content'           => $content,
                'model_used'        => $model,
                'prompt_tokens'     => $usage->promptTokens ?? 0,
                'completion_tokens' => $usage->completionTokens ?? 0,
                'cost_gbp'          => $data['usage']['total_cost_gbp'] ?? 0,
            ]);

            $session->update(['status' => 'complete']);

            return response()->json([
                'session_id' => $session->id,
                'question'   => $session->question,
                'answer'     => $content,
                'model'      => $model,
                'usage'      => $data['usage'] ?? [],
            ]);

        } catch (\Exception $e) {
            Log::error('AskController error', [
                'message' => $e->getMessage(),
                'data'    => $data,
                'trace'   => $e->getTraceAsString(),
            ]);
            $session->update(['status' => 'failed']);
            return response()->json(['error' => 'Advisor call failed', 'message' => $e->getMessage(), 'data' => $data], 500);
        }
    }
}


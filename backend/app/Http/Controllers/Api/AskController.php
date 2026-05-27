<?php
namespace App\Http\Controllers\Api;
use App\Http\Controllers\Controller;
use App\Models\AdvisorResponse;
use App\Models\BoardSession;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class AskController extends Controller
{
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
            $model = config('openrouter.default_model');

            $response = Http::withToken(config('openrouter.api_key'))
                ->baseUrl(config('openrouter.base_uri'))
                ->timeout(60)
                ->post('/chat/completions', [
                    'model'    => $model,
                    'messages' => [
                        ['role' => 'system', 'content' => 'You are a helpful, direct advisor. Answer clearly and concisely.'],
                        ['role' => 'user',   'content' => $validated['question']],
                    ],
                ]);

            $data    = $response->json();
            $content = $data['choices'][0]['message']['content'];
            $usage   = $data['usage'] ?? [];

            AdvisorResponse::create([
                'board_session_id' => $session->id,
                'content'          => $content,
                'model_used'       => $model,
                'prompt_tokens'    => $usage['prompt_tokens'] ?? 0,
                'completion_tokens'=> $usage['completion_tokens'] ?? 0,
                'cost_gbp'         => $usage['total_cost_gbp'] ?? 0,
            ]);

            $session->update(['status' => 'complete']);

            return response()->json([
                'session_id' => $session->id,
                'question'   => $session->question,
                'answer'     => $content,
                'model'      => $model,
                'usage'      => $usage,
            ]);

        } catch (\Exception $e) {
            Log::error('AskController error', [
                'message' => $e->getMessage(),
                'trace'   => $e->getTraceAsString(),
            ]);
            $session->update(['status' => 'failed']);
            return response()->json(['error' => 'Advisor call failed'], 500);
        }
    }
}
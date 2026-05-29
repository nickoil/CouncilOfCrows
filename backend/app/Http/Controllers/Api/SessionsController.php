<?php
namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\BoardSession;
use Illuminate\Http\JsonResponse;

class SessionsController extends Controller
{
    public function index(): JsonResponse
    {
        $sessions = BoardSession::with('advisorResponses')
            ->latest()
            ->limit(20)
            ->get()
            ->map(fn ($s) => [
                'id'         => $s->id,
                'question'   => $s->question,
                'status'     => $s->status,
                'created_at' => $s->created_at,
                'answer'     => $s->advisorResponses->first()?->content,
                'model'      => $s->advisorResponses->first()?->model_used,
            ]);

        return response()->json($sessions);
    }

    public function show(BoardSession $session): JsonResponse
    {
        $session->load('advisorResponses');

        $response = $session->advisorResponses->first();

        return response()->json([
            'session_id' => $session->id,
            'question'   => $session->question,
            'status'     => $session->status,
            'created_at' => $session->created_at,
            'answer'     => $response?->content,
            'model'      => $response?->model_used,
            'usage'      => $response ? [
                'prompt_tokens'     => $response->prompt_tokens,
                'completion_tokens' => $response->completion_tokens,
            ] : [],
        ]);
    }
}

<?php
namespace App\Http\Controllers\Api;
use App\Http\Controllers\Controller;
use App\Services\Orchestrator;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class AskController extends Controller
{
    public function __construct(private readonly Orchestrator $orchestrator) {}

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'question' => 'required|string|max:2000',
        ]);

        try {
            $session = $this->orchestrator->deliberate($validated['question']);
            return response()->json(self::formatSession($session));
        } catch (\Exception $e) {
            Log::error('AskController error', ['message' => $e->getMessage()]);
            return response()->json(['error' => 'Council deliberation failed', 'message' => $e->getMessage()], 500);
        }
    }

    public static function formatSession(\App\Models\BoardSession $session): array
    {
        return [
            'id'                => $session->id,
            'question'          => $session->question,
            'status'            => $session->status,
            'consensus'         => $session->consensus,
            'created_at'        => $session->created_at,
            'advisor_responses' => $session->advisorResponses->map(fn ($r) => [
                'id'         => $r->id,
                'content'    => $r->content,
                'model_used' => $r->model_used,
                'advisor'    => $r->advisor ? [
                    'name' => $r->advisor->name,
                    'role' => $r->advisor->role,
                ] : null,
            ]),
        ];
    }
}


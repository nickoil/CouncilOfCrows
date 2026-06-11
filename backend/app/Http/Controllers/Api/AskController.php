<?php
namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Jobs\RunCouncilDeliberation;
use App\Models\BoardSession;
use App\Support\SessionPresenter;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class AskController extends Controller
{
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'question'          => 'required|string|max:2000',
            'subject'           => 'nullable|string|max:255',
            'deliberation_mode' => 'nullable|in:single_round,two_round',
        ]);

        try {
            $session = BoardSession::create([
                'question'          => $validated['question'],
                'subject'           => $validated['subject'] ?? null,
                'status'            => 'queued',
                'deliberation_mode' => $validated['deliberation_mode'] ?? 'single_round',
            ]);

            RunCouncilDeliberation::dispatch($session->id)->onQueue('debate');

            return response()->json(SessionPresenter::present($session, [
                'phase' => 'queued',
            ]), 202);
        } catch (\Throwable $e) {
            Log::error('AskController error', ['message' => $e->getMessage()]);
            return response()->json(['error' => 'Council deliberation failed', 'message' => $e->getMessage()], 500);
        }
    }

    public static function formatSession(\App\Models\BoardSession $session): array
    {
        return SessionPresenter::present($session);
    }
}


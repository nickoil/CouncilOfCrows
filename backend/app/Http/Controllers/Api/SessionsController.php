<?php
namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\BoardSession;
use App\Support\SessionPresenter;
use Illuminate\Http\JsonResponse;

class SessionsController extends Controller
{
    public function index(): JsonResponse
    {
        $sessions = BoardSession::latest()
            ->limit(20)
            ->get()
            ->map(fn ($s) => [
                'id'         => $s->id,
                'question'   => $s->question,
                'status'     => $s->status,
                'created_at' => $s->created_at,
            ]);

        return response()->json($sessions);
    }

    public function show(BoardSession $session): JsonResponse
    {
        return response()->json(SessionPresenter::present($session));
    }
}

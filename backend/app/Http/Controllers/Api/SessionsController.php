<?php
namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\BoardSession;
use App\Support\SessionPresenter;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SessionsController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $query = BoardSession::query()
            ->orderByRaw('subject ASC NULLS LAST')
            ->orderByDesc('created_at')
            ->limit(50);

        if ($request->filled('subject')) {
            $query->whereRaw('LOWER(subject) = LOWER(?)', [$request->input('subject')]);
        }

        $sessions = $query->get()->map(fn ($s) => [
            'id'         => $s->id,
            'question'   => $s->question,
            'subject'    => $s->subject,
            'status'     => $s->status,
            'created_at' => $s->created_at,
        ]);

        return response()->json($sessions);
    }

    public function show(BoardSession $session): JsonResponse
    {
        return response()->json(SessionPresenter::present($session));
    }

    public function subjects(): JsonResponse
    {
        $subjects = BoardSession::query()
            ->whereNotNull('subject')
            ->where('subject', '!=', '')
            ->select('subject')
            ->distinct()
            ->orderBy('subject')
            ->pluck('subject');

        return response()->json($subjects);
    }
}

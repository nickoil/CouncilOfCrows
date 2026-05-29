<?php

namespace App\Support;

use App\Models\Advisor;
use App\Models\BoardSession;

class SessionPresenter
{
    public static function present(BoardSession $session, ?array $progress = null): array
    {
        $session->load('advisorResponses.advisor');

        return [
            'id'                => $session->id,
            'question'          => $session->question,
            'status'            => $session->status,
            'consensus'         => $session->consensus,
            'created_at'        => $session->created_at,
            'updated_at'        => $session->updated_at,
            'progress'          => $progress,
            'advisors'          => Advisor::where('active', true)
                ->where('role', '!=', 'chair')
                ->orderBy('id')
                ->get()
                ->map(fn ($advisor) => [
                    'id'    => $advisor->id,
                    'name'  => $advisor->name,
                    'role'  => $advisor->role,
                    'model' => $advisor->model,
                ])
                ->values()
                ->all(),
            'advisor_responses' => $session->advisorResponses->map(fn ($response) => [
                'id'         => $response->id,
                'content'    => $response->content,
                'model_used' => $response->model_used,
                'advisor'    => $response->advisor ? [
                    'id'   => $response->advisor->id,
                    'name' => $response->advisor->name,
                    'role' => $response->advisor->role,
                ] : null,
            ])->values()->all(),
        ];
    }
}
<?php

namespace App\Support;

use App\Models\Advisor;
use App\Models\BoardSession;

class SessionPresenter
{
    public static function present(BoardSession $session, ?array $progress = null): array
    {
        $session->load('advisorResponses.advisor');

        $activeAdvisorIds = $session->active_advisor_ids ?? [];
        $activeAdvisors = Advisor::whereIn('id', $activeAdvisorIds)
            ->get()
            ->keyBy('id');

        return [
            'id'                => $session->id,
            'question'          => $session->question,
            'status'            => $session->status,
            'consensus'         => $session->consensus,
            'failure_reason'    => $session->failure_reason,
            'advisor_failures'  => $session->advisor_failures ?? [],
            'partial'           => ! empty($session->advisor_failures),
            'created_at'        => $session->created_at,
            'updated_at'        => $session->updated_at,
            'progress'          => $progress,
            'active_advisors'   => collect($activeAdvisorIds)
                ->map(fn ($advisorId) => $activeAdvisors->get($advisorId))
                ->filter()
                ->map(fn ($advisor) => [
                    'id'    => $advisor->id,
                    'name'  => $advisor->name,
                    'role'  => $advisor->role,
                    'model' => $advisor->model,
                ])
                ->values()
                ->all(),
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
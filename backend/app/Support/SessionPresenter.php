<?php

namespace App\Support;

use App\Models\Advisor;
use App\Models\BoardSession;

class SessionPresenter
{
    public static function present(BoardSession $session, ?array $progress = null): array
    {
        AdvisorCatalog::ensureDefaults();

        $session->load('advisorResponses.advisor');

        $retrievedIds     = $session->retrieved_session_ids ?? [];
        $retrievedMemories = $retrievedIds
            ? BoardSession::whereIn('id', $retrievedIds)
                ->get(['id', 'question', 'created_at'])
                ->map(fn ($s) => [
                    'id'       => $s->id,
                    'question' => $s->question,
                    'date'     => $s->created_at->format('Y-m-d'),
                ])
                ->toArray()
            : [];

        $activeAdvisorIds = $session->active_advisor_ids ?? [];
        $activeAdvisors = Advisor::whereIn('id', $activeAdvisorIds)
            ->get()
            ->keyBy('id');

        return [
            'id'                => $session->id,
            'question'          => $session->question,
            'subject'           => $session->subject,
            'status'            => $session->status,
            'deliberation_mode' => $session->deliberation_mode,
            'consensus'         => $session->consensus,
            'failure_reason'    => $session->failure_reason,
            'advisor_failures'  => $session->advisor_failures ?? [],
            'selected_tensions' => $session->selected_tensions ?? [],
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
            'cost'              => [
                'prompt_tokens'    => (int) $session->advisorResponses->sum('prompt_tokens'),
                'completion_tokens' => (int) $session->advisorResponses->sum('completion_tokens'),
                'total_tokens'     => (int) ($session->advisorResponses->sum('prompt_tokens') + $session->advisorResponses->sum('completion_tokens')),
                'total_cost_gbp'   => round((float) $session->advisorResponses->sum('cost_gbp'), 6),
                'budget_hit'       => (bool) ($session->cost_summary['budget_hit'] ?? false),
                'degradation'      => $session->cost_summary['degradation'] ?? null,
            ],
            'retrieved_memories' => $retrievedMemories,
            'advisor_responses' => $session->advisorResponses->map(fn ($response) => [
                'id'               => $response->id,
                'response_type'    => $response->response_type,
                'round_number'     => $response->round_number,
                'tension_key'      => $response->tension_key,
                'tension_label'    => $response->tension_label,
                'content'          => $response->content,
                'model_used'       => $response->model_used,
                'prompt_tokens'    => $response->prompt_tokens,
                'completion_tokens' => $response->completion_tokens,
                'cost_gbp'         => $response->cost_gbp,
                'advisor'          => $response->advisor ? [
                    'id'   => $response->advisor->id,
                    'name' => $response->advisor->name,
                    'role' => $response->advisor->role,
                ] : null,
            ])->values()->all(),
        ];
    }
}
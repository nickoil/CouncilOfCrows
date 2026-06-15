<?php

namespace App\Support;

use App\Models\AdvisorResponse;
use Illuminate\Support\Facades\DB;

class CostGuard
{
    public function sessionCostGbp(int $sessionId): float
    {
        return (float) AdvisorResponse::where('board_session_id', $sessionId)->sum('cost_gbp');
    }

    public function sessionTotalTokens(int $sessionId): int
    {
        return (int) AdvisorResponse::where('board_session_id', $sessionId)
            ->selectRaw('COALESCE(sum(prompt_tokens + completion_tokens), 0) as total')
            ->value('total');
    }

    public function monthlySpendGbp(?int $year = null, ?int $month = null): float
    {
        $year  ??= now()->year;
        $month ??= now()->month;

        return (float) AdvisorResponse::join('board_sessions', 'board_sessions.id', '=', 'advisor_responses.board_session_id')
            ->whereYear('board_sessions.created_at', $year)
            ->whereMonth('board_sessions.created_at', $month)
            ->sum('advisor_responses.cost_gbp');
    }

    public function status(int $sessionId): BudgetStatus
    {
        $monthlySpend  = $this->monthlySpendGbp();
        $monthlyBudget = (float) config('council.constraints.monthly_cost_budget_gbp');
        $monthlyPct    = $monthlyBudget > 0 ? $monthlySpend / $monthlyBudget : 1.0;
        $warningPct    = (float) config('council.constraints.monthly_cost_warning_pct');

        $sessionSpend      = $this->sessionCostGbp($sessionId);
        $sessionBudget     = (float) config('council.constraints.session_cost_budget_gbp');
        $sessionTokens     = $this->sessionTotalTokens($sessionId);
        $sessionTokenBudget = (int) config('council.constraints.session_token_budget');

        return new BudgetStatus(
            monthly_spend:        $monthlySpend,
            monthly_budget:       $monthlyBudget,
            monthly_pct:          $monthlyPct,
            monthly_exceeded:     $monthlySpend >= $monthlyBudget,
            near_monthly_limit:   $monthlyPct >= $warningPct,
            session_spend:        $sessionSpend,
            session_budget:       $sessionBudget,
            session_tokens:       $sessionTokens,
            session_token_budget: $sessionTokenBudget,
            session_exceeded:     $sessionSpend >= $sessionBudget || $sessionTokens >= $sessionTokenBudget,
        );
    }

    public function monthlyBudgetExceeded(): bool
    {
        return $this->monthlySpendGbp() >= (float) config('council.constraints.monthly_cost_budget_gbp');
    }

    public function estimateForMode(string $mode): array
    {
        $rows = DB::table('advisor_responses')
            ->join('board_sessions', 'board_sessions.id', '=', 'advisor_responses.board_session_id')
            ->where('board_sessions.deliberation_mode', $mode)
            ->where('board_sessions.status', 'complete')
            ->groupBy('advisor_responses.board_session_id')
            ->selectRaw('sum(advisor_responses.cost_gbp) as session_cost')
            ->orderByDesc('board_sessions.created_at')
            ->limit(30)
            ->pluck('session_cost');

        if ($rows->isEmpty()) {
            return [
                'mode'           => $mode,
                'avg_cost_gbp'   => null,
                'min_cost_gbp'   => null,
                'max_cost_gbp'   => null,
                'sample_size'    => 0,
            ];
        }

        return [
            'mode'         => $mode,
            'avg_cost_gbp' => round($rows->avg(), 4),
            'min_cost_gbp' => round($rows->min(), 4),
            'max_cost_gbp' => round($rows->max(), 4),
            'sample_size'  => $rows->count(),
        ];
    }
}

<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Support\CostGuard;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CostController extends Controller
{
    public function __construct(private readonly CostGuard $guard) {}

    public function monthly(): JsonResponse
    {
        $spend  = $this->guard->monthlySpendGbp();
        $budget = (float) config('council.constraints.monthly_cost_budget_gbp');
        $pct    = $budget > 0 ? $spend / $budget : 1.0;

        return response()->json([
            'month'           => now()->format('Y-m'),
            'spend_gbp'       => round($spend, 4),
            'budget_gbp'      => $budget,
            'utilisation_pct' => round($pct * 100, 1),
            'exceeded'        => $spend >= $budget,
            'near_limit'      => $pct >= (float) config('council.constraints.monthly_cost_warning_pct'),
        ]);
    }

    public function estimate(Request $request): JsonResponse
    {
        $mode = $request->query('mode', 'single_round');

        return response()->json($this->guard->estimateForMode($mode));
    }
}

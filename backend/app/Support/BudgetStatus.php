<?php

namespace App\Support;

readonly class BudgetStatus
{
    public function __construct(
        public float $monthly_spend,
        public float $monthly_budget,
        public float $monthly_pct,
        public bool  $monthly_exceeded,
        public bool  $near_monthly_limit,
        public float $session_spend,
        public float $session_budget,
        public int   $session_tokens,
        public int   $session_token_budget,
        public bool  $session_exceeded,
    ) {}
}

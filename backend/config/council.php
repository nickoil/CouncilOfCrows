<?php

return [
    'constraints' => [
        'max_active_advisors'      => 6,
        'max_deliberation_rounds'  => 2,
        'max_retrieval_sessions'   => 3,
        'session_token_budget'     => 50000,
        'session_cost_budget_gbp'  => 2.00,
        'monthly_cost_budget_gbp'  => 100.00,
        'monthly_cost_warning_pct' => 0.80,
    ],

    // OpenRouter returns cost in USD. This rate converts to GBP for storage and display.
    // Update periodically or replace with a live FX call in a future phase.
    'usd_to_gbp_rate' => (float) env('COUNCIL_USD_TO_GBP_RATE', 0.79),

    'embedding' => [
        'model'                => env('COUNCIL_EMBEDDING_MODEL', 'openai/text-embedding-3-small'),
        'dimensions'           => 1536,
        'similarity_threshold' => 0.72,
        'max_retrieved'        => 5,
    ],
];

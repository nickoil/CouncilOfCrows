<?php

namespace App\Support;

use App\Models\AdvisorResponse;
use App\Models\BoardSession;
use Carbon\CarbonInterface;

class SessionBroadcastPayload
{
    public function __construct(
        private readonly int $id,
        private readonly string $question,
        private readonly string $status,
        private readonly ?CarbonInterface $createdAt,
        private readonly ?CarbonInterface $updatedAt,
        private readonly ?array $progress,
        private readonly array $cost,
    ) {}

    public static function fromSession(BoardSession $session, ?array $progress = null): self
    {
        $totalCost   = (float) AdvisorResponse::where('board_session_id', $session->id)->sum('cost_gbp');
        $totalTokens = (int) AdvisorResponse::where('board_session_id', $session->id)
            ->selectRaw('COALESCE(sum(prompt_tokens + completion_tokens), 0) as total')
            ->value('total');

        return new self(
            $session->id,
            $session->question,
            $session->status,
            $session->created_at,
            $session->updated_at,
            $progress,
            ['total_cost_gbp' => round($totalCost, 6), 'total_tokens' => $totalTokens],
        );
    }

    public function toArray(): array
    {
        return [
            'id'         => $this->id,
            'question'   => $this->question,
            'status'     => $this->status,
            'created_at' => $this->createdAt,
            'updated_at' => $this->updatedAt,
            'progress'   => $this->progress,
            'cost'       => $this->cost,
        ];
    }
}

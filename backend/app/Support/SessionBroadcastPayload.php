<?php

namespace App\Support;

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
    ) {}

    public static function fromSession(BoardSession $session, ?array $progress = null): self
    {
        return new self(
            $session->id,
            $session->question,
            $session->status,
            $session->created_at,
            $session->updated_at,
            $progress,
        );
    }

    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'question' => $this->question,
            'status' => $this->status,
            'created_at' => $this->createdAt,
            'updated_at' => $this->updatedAt,
            'progress' => $this->progress,
        ];
    }
}
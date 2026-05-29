<?php

namespace App\Events;

use App\Support\SessionBroadcastPayload;
use Illuminate\Broadcasting\Channel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class CouncilSessionUpdated implements ShouldBroadcastNow
{
    use Dispatchable;
    use SerializesModels;

    public function __construct(
        private readonly int $sessionId,
        private readonly SessionBroadcastPayload $payload,
    ) {}

    public function broadcastOn(): array
    {
        return [new Channel("sessions.{$this->sessionId}")];
    }

    public function broadcastAs(): string
    {
        return 'council.session.updated';
    }

    public function broadcastWith(): array
    {
        return $this->payload->toArray();
    }
}
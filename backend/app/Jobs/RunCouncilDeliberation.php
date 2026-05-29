<?php

namespace App\Jobs;

use App\Models\BoardSession;
use App\Services\Orchestrator;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class RunCouncilDeliberation implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public function __construct(public readonly int $sessionId) {}

    public function handle(Orchestrator $orchestrator): void
    {
        $session = BoardSession::findOrFail($this->sessionId);

        $orchestrator->deliberate($session);
    }
}
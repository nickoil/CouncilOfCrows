<?php

namespace App\Jobs;

use App\Models\Advisor;
use App\Models\BoardSession;
use App\Services\Orchestrator;
use Illuminate\Bus\Batchable;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class CallAdvisorJob implements ShouldQueue
{
    use Batchable;
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $tries = 1;

    public function __construct(
        public readonly int $sessionId,
        public readonly int $advisorId,
    ) {}

    public function handle(Orchestrator $orchestrator): void
    {
        if ($this->batch()?->cancelled()) {
            return;
        }

        $session = BoardSession::findOrFail($this->sessionId);
        $advisor = Advisor::findOrFail($this->advisorId);

        $orchestrator->handleAdvisor($session, $advisor);
    }
}
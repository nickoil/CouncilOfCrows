<?php

namespace App\Jobs;

use App\Models\Advisor;
use App\Models\BoardSession;
use Illuminate\Bus\Batch;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Bus;
use App\Services\Orchestrator;

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

        $advisors = Advisor::where('active', true)
            ->where('role', '!=', 'chair')
            ->orderBy('id')
            ->get();

        Advisor::where('role', 'chair')
            ->where('active', true)
            ->firstOrFail();

        $orchestrator->prepareSession($session, $advisors);

        $jobs = $advisors
            ->map(fn ($advisor) => (new CallAdvisorJob($session->id, $advisor->id))->onQueue('debate'))
            ->all();

        Bus::batch($jobs)
            ->name("Council session {$session->id}")
            ->allowFailures()
            ->finally(function (Batch $batch) use ($session): void {
                FinalizeCouncilDeliberation::dispatch($session->id)->onQueue('debate');
            })
            ->dispatch();
    }
}
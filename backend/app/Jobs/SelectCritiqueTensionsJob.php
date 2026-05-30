<?php

namespace App\Jobs;

use App\Jobs\CallCritiqueJob;
use App\Jobs\FinalizeCouncilDeliberation;
use App\Models\Advisor;
use App\Models\BoardSession;
use App\Services\Orchestrator;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Bus;

class SelectCritiqueTensionsJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $tries = 1;

    public int $timeout = 300;

    public bool $failOnTimeout = true;

    public function __construct(public readonly int $sessionId) {}

    public function handle(Orchestrator $orchestrator): void
    {
        $session = BoardSession::findOrFail($this->sessionId);
        $tensions = $orchestrator->selectCritiqueTensions($session);

        if ($tensions === []) {
            FinalizeCouncilDeliberation::dispatch($session->id)->onQueue('debate');

            return;
        }

        $session->update(['status' => 'critiquing']);

        $advisors = Advisor::where('active', true)
            ->where('role', '!=', 'chair')
            ->orderBy('id')
            ->get();

        $jobs = $advisors
            ->values()
            ->map(function ($advisor, $index) use ($session, $tensions) {
                $tension = $tensions[$index % count($tensions)];

                return (new CallCritiqueJob($session->id, $advisor->id, $tension))->onQueue('debate');
            })
            ->all();

        Bus::batch($jobs)
            ->name("Council session {$session->id} critiques")
            ->allowFailures()
            ->dispatch();
    }
}
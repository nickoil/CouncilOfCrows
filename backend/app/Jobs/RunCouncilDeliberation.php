<?php

namespace App\Jobs;

use App\Events\CouncilSessionUpdated;
use App\Models\Advisor;
use App\Models\BoardSession;
use App\Services\SemanticMemoryService;
use App\Support\AdvisorCatalog;
use App\Support\CostGuard;
use App\Support\SessionBroadcastPayload;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Log;
use App\Services\Orchestrator;

class RunCouncilDeliberation implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public function __construct(public readonly int $sessionId) {}

    public function handle(Orchestrator $orchestrator, CostGuard $guard): void
    {
        $session = BoardSession::findOrFail($this->sessionId);

        if ($guard->monthlyBudgetExceeded()) {
            $reason = 'Monthly cost budget exceeded. No new deliberations until next billing period.';

            $session->update(['status' => 'failed', 'failure_reason' => $reason]);

            event(new CouncilSessionUpdated(SessionBroadcastPayload::fromSession($session->fresh(), [
                'phase' => 'failed',
                'error' => $reason,
            ])));

            return;
        }

        AdvisorCatalog::ensureDefaults();

        $maxAdvisors = (int) config('council.constraints.max_active_advisors');

        $advisors = Advisor::where('active', true)
            ->where('role', '!=', 'chair')
            ->orderBy('id')
            ->take($maxAdvisors)
            ->get();

        $chair = Advisor::where('role', 'chair')
            ->where('active', true)
            ->firstOrFail();

        if ($advisors->isEmpty()) {
            throw new \RuntimeException('No active non-chair advisors are configured.');
        }

        $session = $orchestrator->prepareSession($session, $advisors);

        try {
            $memoryService = app(SemanticMemoryService::class);
            $context       = $memoryService->buildContextForSession($session);
            if ($context !== null) {
                $session->update([
                    'memory_context'        => $context['block'],
                    'retrieved_session_ids' => $context['session_ids'],
                ]);
                $session->refresh();
            }
        } catch (\Throwable $e) {
            Log::warning('[Council] Semantic memory retrieval failed, continuing without it', [
                'session_id' => $session->id,
                'error'      => $e->getMessage(),
            ]);
        }

        $jobs = $advisors
            ->map(fn ($advisor) => (new CallAdvisorJob($session->id, $advisor->id))->onQueue('debate'))
            ->all();

        Bus::batch($jobs)
            ->name("Council session {$session->id}")
            ->allowFailures()
            ->dispatch();
    }
}

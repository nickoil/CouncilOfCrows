<?php

namespace App\Jobs;

use App\Models\Advisor;
use App\Models\AdvisorResponse;
use App\Models\BoardSession;
use App\Support\CostGuard;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\Middleware\WithoutOverlapping;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class AdvanceCouncilStage implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $tries = 1;

    public function __construct(public readonly int $sessionId) {}

    public function middleware(): array
    {
        return [(new WithoutOverlapping("council-stage-{$this->sessionId}"))->dontRelease()];
    }

    public function handle(): void
    {
        $session = BoardSession::find($this->sessionId);

        if (! $session) {
            return;
        }

        $totalAdvisors = Advisor::where('active', true)
            ->where('role', '!=', 'chair')
            ->count();

        if ($totalAdvisors === 0) {
            return;
        }

        $hasSummary = AdvisorResponse::query()
            ->where('board_session_id', $session->id)
            ->where('response_type', 'chair_summary')
            ->exists();

        if ($hasSummary || in_array($session->status, ['complete', 'failed'], true)) {
            return;
        }

        $independentCompleted = AdvisorResponse::query()
            ->where('board_session_id', $session->id)
            ->where('response_type', 'independent')
            ->count();

        $independentFailed = collect($session->advisor_failures ?? [])
            ->filter(fn (array $failure) => ($failure['response_type'] ?? 'independent') === 'independent')
            ->count();

        if (($independentCompleted + $independentFailed) < $totalAdvisors) {
            return;
        }

        if ($session->deliberation_mode === 'single_round') {
            if ($session->status === 'processing') {
                Log::info('[Council] Advancing to final synthesis', ['session_id' => $session->id]);
                FinalizeCouncilDeliberation::dispatch($session->id)->onQueue('debate');
            }

            return;
        }

        $critiqueCompleted = AdvisorResponse::query()
            ->where('board_session_id', $session->id)
            ->where('response_type', 'critique')
            ->count();

        $critiqueFailed = collect($session->advisor_failures ?? [])
            ->filter(fn (array $failure) => ($failure['response_type'] ?? null) === 'critique')
            ->count();

        if ($session->status === 'processing' && empty($session->selected_tensions)) {
            $guard  = new CostGuard();
            $status = $guard->status($session->id);

            if ($status->session_exceeded) {
                Log::warning('[Council] Session budget exceeded after round 1; skipping critique round', [
                    'session_id'   => $session->id,
                    'session_cost' => $status->session_spend,
                    'session_tokens' => $status->session_tokens,
                ]);

                $session->update([
                    'cost_summary' => [
                        'budget_hit'                 => true,
                        'degradation'                => 'Critique round skipped: session budget exceeded after round 1.',
                        'monthly_cost_at_start_gbp'  => $status->monthly_spend,
                    ],
                ]);

                FinalizeCouncilDeliberation::dispatch($session->id)->onQueue('debate');

                return;
            }

            $session->update(['status' => 'selecting_tensions']);

            Log::info('[Council] Advancing to tension selection', ['session_id' => $session->id]);
            SelectCritiqueTensionsJob::dispatch($session->id)->onQueue('debate');

            return;
        }

        if ($session->status === 'critiquing' && ($critiqueCompleted + $critiqueFailed) >= $totalAdvisors) {
            Log::info('[Council] Advancing to final synthesis after critiques', ['session_id' => $session->id]);
            FinalizeCouncilDeliberation::dispatch($session->id)->onQueue('debate');
        }
    }
}
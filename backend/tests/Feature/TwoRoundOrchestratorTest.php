<?php

namespace Tests\Feature;

use App\Events\CouncilSessionUpdated;
use App\Models\Advisor;
use App\Models\BoardSession;
use App\Services\OpenRouterClient;
use App\Services\Orchestrator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Mockery;
use Tests\TestCase;

class TwoRoundOrchestratorTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_runs_the_two_round_deliberation_flow(): void
    {
        Event::fake([CouncilSessionUpdated::class]);

        $chair = Advisor::create([
            'name' => 'Chair',
            'role' => 'chair',
            'description' => 'Synthesises the council output.',
            'system_prompt' => 'You chair the council.',
            'model' => 'chair-model',
            'active' => true,
        ]);

        $strategist = Advisor::create([
            'name' => 'Strategist',
            'role' => 'strategist',
            'description' => 'Optimises strategic choices.',
            'system_prompt' => 'You are the strategist.',
            'model' => 'advisor-model-a',
            'active' => true,
        ]);

        $skeptic = Advisor::create([
            'name' => 'Skeptic',
            'role' => 'skeptic',
            'description' => 'Challenges weak assumptions.',
            'system_prompt' => 'You are the skeptic.',
            'model' => 'advisor-model-b',
            'active' => true,
        ]);

        $openRouter = Mockery::mock(OpenRouterClient::class);
        $openRouter->shouldReceive('createChat')->times(6)->andReturn(
            $this->fakeChatResult('Round 1 from strategist.'),
            $this->fakeChatResult('Round 1 from skeptic.'),
            $this->fakeChatResult(json_encode([
                'tensions' => [[
                    'key' => 'trade-off',
                    'label' => 'Speed versus safety',
                    'question' => 'Which trade-off should dominate the final recommendation?',
                    'advisors_involved' => ['strategist', 'skeptic'],
                    'why_it_matters' => 'The council needs to decide whether speed or risk control matters more.',
                ]],
            ], JSON_THROW_ON_ERROR)),
            $this->fakeChatResult('Critique from strategist.'),
            $this->fakeChatResult('Critique from skeptic.'),
            $this->fakeChatResult('Chair synthesis.')
        );

        $orchestrator = new Orchestrator($openRouter);

        $session = BoardSession::create([
            'question' => 'How should we act under uncertainty?',
            'status' => 'queued',
            'deliberation_mode' => 'two_round',
        ]);

        $advisors = collect([$strategist, $skeptic]);

        $session = $orchestrator->prepareSession($session, $advisors);
        $orchestrator->handleIndependentAdvisor($session, $strategist);
        $orchestrator->handleIndependentAdvisor($session, $skeptic);

        $tensions = $orchestrator->selectCritiqueTensions($session->fresh());

        $this->assertCount(1, $tensions);
        $this->assertSame('trade-off', $tensions[0]['key']);

        $orchestrator->handleCritiqueAdvisor($session->fresh(), $strategist, $tensions[0]);
        $orchestrator->handleCritiqueAdvisor($session->fresh(), $skeptic, $tensions[0]);

        $final = $orchestrator->finalize($session->fresh())->fresh('advisorResponses');

        $this->assertSame('complete', $final->status);
        $this->assertSame('Chair synthesis.', $final->consensus);
        $this->assertCount(1, $final->selected_tensions ?? []);
        $this->assertCount(5, $final->advisorResponses);
        $this->assertDatabaseHas('advisor_responses', [
            'board_session_id' => $final->id,
            'advisor_id' => $chair->id,
            'response_type' => 'chair_summary',
        ]);
        $this->assertDatabaseHas('advisor_responses', [
            'board_session_id' => $final->id,
            'advisor_id' => $strategist->id,
            'response_type' => 'critique',
            'tension_key' => 'trade-off',
        ]);
    }

    protected function tearDown(): void
    {
        Mockery::close();

        parent::tearDown();
    }

    private function fakeChatResult(string $content): object
    {
        return new class ($content) {
            public array $choices;

            public object $usage;

            private string $content;

            public function __construct(string $content)
            {
                $this->content = $content;
                $this->choices = [
                    (object) [
                        'message' => (object) ['content' => $content],
                    ],
                ];
                $this->usage = (object) [
                    'promptTokens' => 12,
                    'completionTokens' => 34,
                ];
            }

            public function toArray(): array
            {
                return [
                    'choices' => [
                        [
                            'message' => ['content' => $this->content],
                        ],
                    ],
                    'usage' => [
                        'total_cost_gbp' => 0,
                    ],
                ];
            }
        };
    }
}
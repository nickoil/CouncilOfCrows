<?php

namespace Tests\Feature;

use App\Jobs\RunCouncilDeliberation;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class AskControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_accepts_the_deliberation_mode_toggle(): void
    {
        Queue::fake();

        $response = $this->postJson('/api/ask', [
            'question' => 'Should the council use one or two rounds?',
            'deliberation_mode' => 'two_round',
        ]);

        $response->assertAccepted()
            ->assertJsonPath('question', 'Should the council use one or two rounds?')
            ->assertJsonPath('deliberation_mode', 'two_round')
            ->assertJsonPath('status', 'queued');

        $this->assertDatabaseHas('board_sessions', [
            'question' => 'Should the council use one or two rounds?',
            'deliberation_mode' => 'two_round',
            'status' => 'queued',
        ]);

        Queue::assertPushed(RunCouncilDeliberation::class, 1);
    }
}
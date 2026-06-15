<?php

namespace App\Jobs;

use App\Models\BoardSession;
use App\Services\SemanticMemoryService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class GenerateSessionEmbeddingJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $tries = 2;

    public int $timeout = 60;

    public function __construct(public readonly int $sessionId) {}

    public function handle(SemanticMemoryService $memory): void
    {
        $session = BoardSession::findOrFail($this->sessionId);
        $memory->storeSessionEmbedding($session);
    }
}

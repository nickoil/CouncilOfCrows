<?php

namespace App\Services;

use App\Models\BoardSession;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class SemanticMemoryService
{
    public function embed(string $text): array
    {
        $response = Http::withToken(config('openrouter.api_key'))
            ->withHeaders([
                'HTTP-Referer' => config('openrouter.site_url'),
                'X-Title'      => config('openrouter.site_name'),
            ])
            ->post(config('openrouter.base_uri') . '/embeddings', [
                'model' => config('council.embedding.model'),
                'input' => $text,
            ]);

        if ($response->failed()) {
            throw new \RuntimeException('OpenRouter embeddings API error: ' . $response->body());
        }

        return $response->json('data.0.embedding');
    }

    public function findSimilarSessions(array $queryEmbedding, int $excludeSessionId): array
    {
        $threshold = (float) config('council.embedding.similarity_threshold', 0.72);
        $limit     = (int) config('council.embedding.max_retrieved', 5);
        $vector    = json_encode($queryEmbedding);

        return DB::select(
            "SELECT me.board_session_id,
                    bs.question,
                    bs.consensus,
                    bs.created_at,
                    1 - (me.embedding <=> ?::vector) AS similarity
             FROM memory_embeddings me
             JOIN board_sessions bs ON bs.id = me.board_session_id
             WHERE me.board_session_id != ?
               AND me.content_type = 'deliberation'
               AND bs.status = 'complete'
               AND bs.consensus IS NOT NULL
               AND 1 - (me.embedding <=> ?::vector) >= ?
             ORDER BY me.embedding <=> ?::vector
             LIMIT ?",
            [$vector, $excludeSessionId, $vector, $threshold, $vector, $limit]
        );
    }

    public function buildContextForSession(BoardSession $session): ?array
    {
        $queryEmbedding = $this->embed($session->question);
        $rows = $this->findSimilarSessions($queryEmbedding, $session->id);

        if (empty($rows)) {
            return null;
        }

        $parts      = ['Semantically related prior deliberations:'];
        $sessionIds = [];

        foreach ($rows as $row) {
            $similarity  = (int) round($row->similarity * 100);
            $date        = (new \DateTime($row->created_at))->format('Y-m-d');
            $consensus   = mb_strlen($row->consensus) > 400
                ? mb_substr($row->consensus, 0, 400) . '…'
                : $row->consensus;

            $parts[]      = "— {$date} [{$similarity}% relevant]: \"{$row->question}\"\nSynthesis: {$consensus}";
            $sessionIds[] = $row->board_session_id;
        }

        return [
            'block'       => implode("\n\n", $parts),
            'session_ids' => $sessionIds,
        ];
    }

    public function storeSessionEmbedding(BoardSession $session): void
    {
        if (empty($session->question) || empty($session->consensus)) {
            Log::warning('[SemanticMemory] Skipping embedding — missing question or consensus', [
                'session_id' => $session->id,
            ]);

            return;
        }

        $consensusTruncated = mb_substr($session->consensus, 0, 600);
        $combinedText       = "Question: {$session->question}\nCouncil conclusion: {$consensusTruncated}";
        $embedding          = $this->embed($combinedText);
        $model              = config('council.embedding.model');

        DB::statement(
            'INSERT INTO memory_embeddings (board_session_id, content_type, content, model, embedding, created_at, updated_at)
             VALUES (?, ?, ?, ?, ?::vector, NOW(), NOW())',
            [$session->id, 'deliberation', $combinedText, $model, json_encode($embedding)]
        );

        Log::info('[SemanticMemory] Embedding stored', [
            'session_id' => $session->id,
            'model'      => $model,
        ]);
    }
}

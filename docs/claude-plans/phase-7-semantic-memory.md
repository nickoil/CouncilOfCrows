# Phase 7: Semantic Memory

## Context

Phases 1–6 established cost-bounded deliberation with subject-scoped episodic memory. The current
`buildPriorContextBlock()` (Orchestrator.php:79) retrieves prior sessions by exact subject-string
match — cross-subject retrieval is impossible, and untagged questions get no memory at all.

Phase 7 replaces this with a pgvector embedding pipeline: every completed session's question is
embedded and stored; before each new deliberation the question is embedded and used to retrieve
semantically similar past consensuses, regardless of subject. The UI surfaces which prior
deliberations are being drawn on.

pgvector is already installed and enabled. There are no embedding tables, services, or jobs yet.
The OpenRouter API key is already in the environment — the same key works for the embeddings
endpoint, so no new credentials or Docker services are needed.

---

## Architecture Decisions

**Embedding provider**: OpenRouter (`POST /v1/embeddings`) with `openai/text-embedding-3-small`
(1536 dimensions). Uses the same API key already in config. No Ollama / new Docker service needed.

**What is embedded**: A combined text of the session `question` + truncated `consensus` (max 600
chars). This ensures retrieval matches on both what was asked AND what was concluded, catching
cases where a future question's phrasing differs from a past question but maps directly to a past
conclusion. At search time the new question alone is embedded (no consensus yet); the asymmetric
match is intentional and well-supported by modern embedding models.

**Memory context pre-computation**: `RunCouncilDeliberation` computes the context block once before
dispatching advisor jobs, persists it on the session, and each advisor job reads it. Avoids N
identical embedding calls per session.

**Graceful fallback chain**:
1. Semantic context from `board_sessions.memory_context` (Phase 7 path)
2. Subject-scoped exact match (Phase 5 fallback when no embeddings exist yet)
3. Nothing (first few sessions before any embeddings are stored)

**Embedding storage timing**: The current session's embedding is stored AFTER it completes (via a
job dispatched from `FinalizeCouncilDeliberation`). Future sessions then find it. No re-embedding
of historical sessions is needed for Phase 7; they accumulate organically.

---

## Implementation Plan

### Step 1 — Config

**Modified: `backend/config/council.php`**

Add an `embedding` block:

```php
'embedding' => [
    'model'                => env('COUNCIL_EMBEDDING_MODEL', 'openai/text-embedding-3-small'),
    'dimensions'           => 1536,
    'similarity_threshold' => 0.72,   // cosine similarity floor (below = excluded)
    'max_retrieved'        => 5,
],
```

---

### Step 2 — Database Migrations

**New migration: `create_memory_embeddings_table`**

```php
$table->id();
$table->foreignId('board_session_id')->constrained()->cascadeOnDelete();
$table->string('content_type', 50)->default('deliberation');  // 'deliberation' = question+consensus combined
$table->text('content');              // original text that was embedded (for display)
$table->string('model');             // embedding model used
$table->timestamps();
// Vector column requires raw SQL (pgvector type not native to Blueprint):
DB::statement('ALTER TABLE memory_embeddings ADD COLUMN embedding vector(1536) NOT NULL');
```

No ANN index for Phase 7 (small dataset; exact search is fast enough via pgvector's default).
Can be added later: `CREATE INDEX … USING hnsw (embedding vector_cosine_ops)`.

**New migration: `add_memory_context_to_board_sessions`**

```php
$table->text('memory_context')->nullable();
$table->json('retrieved_session_ids')->nullable();
```

---

### Step 3 — MemoryEmbedding Model

**New file: `backend/app/Models/MemoryEmbedding.php`**

Simple Eloquent model with `$fillable` = `['board_session_id', 'content_type', 'content', 'model']`.
Note: the `embedding` column is NOT in `$fillable` — it is written only via raw SQL to avoid
Eloquent's string-casting of the vector type.

Add `boardSession()` BelongsTo relationship.

---

### Step 4 — SemanticMemoryService

**New file: `backend/app/Services/SemanticMemoryService.php`**

One service that owns all embedding-related logic:

```php
class SemanticMemoryService
{
    // Call OpenRouter POST /v1/embeddings; return float[]
    public function embed(string $text): array

    // pgvector cosine distance search; returns array of stdClass rows with:
    // board_session_id, question, consensus, created_at, similarity (1 - distance)
    // Filters: excludeSessionId, similarity >= threshold, status='complete', consensus IS NOT NULL
    public function findSimilarSessions(array $queryEmbedding, int $excludeSessionId): array

    // Orchestrates embed → findSimilar → format context block
    // Returns ['block' => string, 'session_ids' => int[]] or null if no matches
    public function buildContextForSession(BoardSession $session): ?array

    // Store the session's question+consensus embedding in memory_embeddings (after session completes)
    public function storeSessionEmbedding(BoardSession $session): void
}
```

**`embed()`**: Direct `Http::withToken(config('services.openrouter.api_key'))` call to
`https://openrouter.ai/api/v1/embeddings`. Do not go through openai-php/client; direct HTTP is
simpler for this endpoint.

**`findSimilarSessions()`**: Raw `DB::select()` using pgvector's `<=>` cosine distance operator:

```sql
SELECT me.board_session_id,
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
LIMIT ?
```

Parameters: `[json_encode($embedding), $excludeSessionId, json_encode($embedding), $threshold, json_encode($embedding), $limit]`

**`buildContextForSession()`**: Formats results as:

```
Semantically related prior deliberations:

— 2026-05-14 [87% relevant]: "Should we raise Series A now or wait?"
Synthesis: The council concluded that waiting 6 months carried less dilution risk…

— 2026-04-22 [79% relevant]: "How should we approach investor relations?"
Synthesis: …
```

**`storeSessionEmbedding()`**: Builds a combined text of question + truncated consensus, embeds it,
then raw insert:

```php
$consensusTruncated = mb_substr($session->consensus ?? '', 0, 600);
$combinedText = "Question: {$session->question}\nCouncil conclusion: {$consensusTruncated}";
$embedding = $this->embed($combinedText);

DB::statement(
    'INSERT INTO memory_embeddings (board_session_id, content_type, content, model, embedding, created_at, updated_at)
     VALUES (?, ?, ?, ?, ?::vector, NOW(), NOW())',
    [$session->id, 'deliberation', $combinedText, config('council.embedding.model'), json_encode($embedding)]
);
```

Note: `content_type` is `'deliberation'` (not `'question'`) to reflect that both question and
consensus are encoded. The search query (`findSimilarSessions`) filters on this value.

---

### Step 5 — GenerateSessionEmbeddingJob

**New file: `backend/app/Jobs/GenerateSessionEmbeddingJob.php`**

```php
public function handle(SemanticMemoryService $memory): void
{
    $session = BoardSession::findOrFail($this->sessionId);
    $memory->storeSessionEmbedding($session);
}
```

Runs on `'debate'` queue. `$tries = 2`, `$timeout = 60`. Failure is non-fatal (just means this
session won't appear in future retrieval; next session still works).

---

### Step 6 — Wire into Existing Jobs

**Modified: `backend/app/Jobs/FinalizeCouncilDeliberation.php`**

After `$orchestrator->finalize($session)`, dispatch the embedding job:

```php
GenerateSessionEmbeddingJob::dispatch($session->id)->onQueue('debate');
```

**Modified: `backend/app/Jobs/RunCouncilDeliberation.php`**

After the monthly budget pre-flight check, before dispatching advisor jobs, add:

```php
try {
    $memoryService = app(SemanticMemoryService::class);
    $context = $memoryService->buildContextForSession($session);
    if ($context !== null) {
        $session->update([
            'memory_context'        => $context['block'],
            'retrieved_session_ids' => $context['session_ids'],
        ]);
        $session->refresh();
    }
} catch (\Throwable $e) {
    Log::warning('Semantic memory retrieval failed, continuing without it', [
        'session_id' => $session->id,
        'error'      => $e->getMessage(),
    ]);
}
```

The session is then passed (refreshed) into advisor job dispatch. Each `CallAdvisorJob` reads
`$session->memory_context` through the existing `buildPriorContextBlock()`.

---

### Step 7 — Update Orchestrator

**Modified: `backend/app/Services/Orchestrator.php` — `buildPriorContextBlock()` (line 79)**

Prepend a semantic check before the existing subject-match logic:

```php
private function buildPriorContextBlock(BoardSession $session): ?string
{
    // Phase 7: use pre-computed semantic context when available
    if (!empty($session->memory_context)) {
        return $session->memory_context;
    }

    // Phase 5 fallback: exact subject match
    if (empty($session->subject)) {
        return null;
    }
    // … existing subject-scoped query unchanged …
}
```

No other changes to Orchestrator.

---

### Step 8 — Update SessionPresenter

**Modified: `backend/app/Support/SessionPresenter.php`**

Add a `retrieved_memories` key to the session response:

```php
$retrievedIds = $session->retrieved_session_ids ?? [];
$retrievedMemories = $retrievedIds
    ? BoardSession::whereIn('id', $retrievedIds)
        ->get(['id', 'question', 'created_at'])
        ->map(fn($s) => [
            'id'       => $s->id,
            'question' => $s->question,
            'date'     => $s->created_at->format('Y-m-d'),
        ])
        ->toArray()
    : [];

// Include in session array:
'retrieved_memories' => $retrievedMemories,
```

---

### Step 9 — Update BoardSession Model

**Modified: `backend/app/Models/BoardSession.php`**

Add to `$fillable`: `'memory_context'`, `'retrieved_session_ids'`
Add to `$casts`: `'retrieved_session_ids' => 'array'`

---

### Step 10 — Frontend

**Modified: `frontend/src/lib/helpers/api.js`**

Add typedef:
```javascript
/**
 * @typedef {{ id: number, question: string, date: string }} RetrievedMemory
 */
```

Update `CouncilSession` typedef to include `retrieved_memories?: RetrievedMemory[]`.

**New file: `frontend/src/lib/components/MemoryContext.svelte`**

Props: `memories: RetrievedMemory[]`

Renders a subtle "Drawing on X prior deliberation(s)" header with a collapsible list of the
retrieved questions and their dates. Only renders when `memories.length > 0`.

```svelte
{#if memories.length > 0}
    <details class="mt-4 text-sm text-gray-500">
        <summary class="cursor-pointer select-none">
            Drawing on {memories.length} prior deliberation{memories.length === 1 ? '' : 's'}
        </summary>
        <ul class="mt-2 space-y-1 pl-4">
            {#each memories as m}
                <li class="list-disc">
                    <span class="text-gray-400">{m.date}</span> — {m.question}
                </li>
            {/each}
        </ul>
    </details>
{/if}
```

**Modified: `frontend/src/lib/components/CouncilView.svelte`**

Import `MemoryContext` and render it after the question display, before the advisor response list:

```svelte
<MemoryContext memories={session.retrieved_memories ?? []} />
```

---

## Files to Create

| File | Purpose |
|------|---------|
| `backend/database/migrations/…_create_memory_embeddings_table.php` | pgvector table for question+consensus embeddings |
| `backend/database/migrations/…_add_memory_context_to_board_sessions.php` | memory_context + retrieved_session_ids columns |
| `backend/app/Models/MemoryEmbedding.php` | Eloquent model (read/display; writes via raw SQL) |
| `backend/app/Services/SemanticMemoryService.php` | embed, retrieve, format, store |
| `backend/app/Jobs/GenerateSessionEmbeddingJob.php` | Stores embedding after session completes |
| `frontend/src/lib/components/MemoryContext.svelte` | "Drawing on X prior deliberations" UI |

## Files to Modify

| File | Change |
|------|--------|
| `backend/config/council.php` | Add `embedding` section |
| `backend/app/Models/BoardSession.php` | Add `memory_context`, `retrieved_session_ids` to fillable/casts |
| `backend/app/Services/Orchestrator.php` | `buildPriorContextBlock()`: check `memory_context` first |
| `backend/app/Jobs/RunCouncilDeliberation.php` | Build + store semantic context before dispatching advisors |
| `backend/app/Jobs/FinalizeCouncilDeliberation.php` | Dispatch `GenerateSessionEmbeddingJob` after finalize |
| `backend/app/Support/SessionPresenter.php` | Expose `retrieved_memories` array |
| `frontend/src/lib/helpers/api.js` | Add `RetrievedMemory` typedef; update `CouncilSession` |
| `frontend/src/lib/components/CouncilView.svelte` | Render `<MemoryContext />` |

---

## Verification

1. **Embedding stored after session**: Complete a deliberation → check `memory_embeddings` table has one
   row with `board_session_id` matching the session and `embedding` not null.

2. **Semantic retrieval fires**: Submit a second question on a related topic (different subject or no
   subject) → check `board_sessions.memory_context` is populated and references the first session.

3. **Context injected into prompts**: Enable `Log::info` in `buildPriorContextBlock()` (temp) → run a
   deliberation → confirm "Semantically related prior deliberations" appears in the logged prompt.

4. **Graceful fallback**: Drop all rows from `memory_embeddings` → run a deliberation with a matching
   subject → confirm subject-based fallback kicks in (existing Phase 5 behaviour).

5. **Graceful failure**: Temporarily set a bad `COUNCIL_EMBEDDING_MODEL` → run a deliberation →
   confirm it completes normally (just without semantic context; warning logged).

6. **UI display**: After two related sessions complete, select the second session from the list →
   "Drawing on 1 prior deliberation" expander appears with the first session's question.

7. **Existing tests still pass**: `TwoRoundOrchestratorTest` should pass unmodified — the new
   `memory_context` column is nullable so existing fixtures require no update.

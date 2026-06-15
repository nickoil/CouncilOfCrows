# Phase History

### Phase 1 — Foundation (done)
Docker stack running, one LLM call functioning, response persisted

### Phase 2 — Multiple Advisors, One Round (done)
Persistent council structure with multiple independent advisor responses

### Phase 3 — Real-Time Deliberation UI (done)
WebSocket broadcasting, deliberation visible as live institutional process

### Phase 4 — Structured Debate Rounds (done)
Staged deliberation: critique rounds, revision, synthesis; advisors run as parallel jobs

### Phase 5 — Subjects and Institutional Memory (done)
Subjects table, subject-scoped sessions, episodic memory items extracted from completed sessions, memory context injected into advisor prompts, subject history and session detail pages

### Phase 6 — Cost Tracking and Deliberative Constraints (done)
Per-response and per-session cost tracking via OpenRouter model pricing API; config-driven governance constraints (max advisors, session/monthly budgets, retrieval limit); graceful degradation when session budget exceeded (critique round skipped, synthesis notes omission); monthly budget pre-flight block; session cost visible in UI with live WebSocket updates; cost estimate shown before submission; monthly budget widget with warning threshold

### Phase 7 — Semantic Memory (done)
Embeddings pipeline via OpenRouter (text-embedding-3-small); question+consensus combined text embedded per session; pgvector cosine similarity retrieval; memory_context pre-computed before advisor jobs dispatch; graceful fallback to subject-scoped retrieval; "Drawing on X prior deliberations" UI expander

### Phase 8 — Structured Memory (next)
Memory extractor LLM pass after each session; human review/approval gate for proposed items; typed records (requirements, decisions, open questions, user facts) stored in dedicated tables; structured memory injected before semantic search in fixed priority order; Subject Memory Panel in UI

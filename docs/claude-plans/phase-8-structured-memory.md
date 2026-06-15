# Phase 8: Structured Memory

> Documentation update plan — describes changes to foundation.md, roadmap.md, phase-history.md, and CLAUDE.md.
> Implementation plan to follow when development begins.

## Context

Phases 1–7 are complete. Phase 7 delivered semantic embeddings (pgvector similarity search).
The user has specified a structured memory design that should be recorded in the project's
foundation and roadmap documents before implementation begins. This is a documentation-only task.

The structured memory concept adds a layer above semantic search: typed, human-curated memory
objects (requirements, decisions, open questions, user facts) that are always injected reliably
before embedding retrieval, preventing critical constraints from being missed because they fell
below a similarity threshold.

---

## Changes Made

### `docs/roadmap.md`

- Phase 8 → Phase 9 (Depth Modes), Phase 9 → Phase 10 (Epistemic Tracking), Phase 10 → Phase 11 (Human Feedback)
- New Phase 8 — Structured Memory inserted

### `docs/foundation.md`

- New Section 5.3 Structured Memory Objects added (existing 5.3–5.6 renumbered to 5.4–5.7)
- New Phase 2a in Section 9 Development Roadmap

### `docs/phase-history.md`

- Phase 7 marked done
- Phase 8 added as next

### `CLAUDE.md`

- Current phase updated to Phase 8
- Architecture decision updated to three-layer memory model
- Structured retrieval order noted

---

## Phase 8 Implementation Notes (for future implementation session)

### Extraction Job

After each completed session (dispatched from `FinalizeCouncilDeliberation`), run an LLM extractor:
- cheapest model (Gemini Flash Lite or equivalent)
- system prompt: "Return strict JSON only"
- input: session question + consensus
- output schema: requirements[], decisions[], open_questions[], user_facts[]

### New Tables

```sql
requirements      (id, subject_id, title, status, confidence, source_session_id, created_at, last_confirmed_at)
decisions         (id, subject_id, title, rationale, status, confidence, source_session_id, created_at)
open_questions    (id, subject_id, question, status, source_session_id, created_at)
user_facts        (id, subject_id, fact, scope, stability, status, source_session_id, created_at)
memory_evidence_links (id, memory_type, memory_id, session_id, excerpt)
```

Status enum: proposed | active | superseded | archived | rejected

### Human Review API

```
GET  /api/subjects/{subject}/proposed-memories   → list pending items
POST /api/memories/approve                        → approve batch
POST /api/memories/{id}/reject
```

### Retrieval Injection Order (in Orchestrator)

1. Active requirements for subject
2. Active decisions for subject
3. Open questions for subject
4. Semantic embeddings (pgvector similarity, existing)
5. Recent episodes (subject-scoped, existing)

### UI

- Subject Memory Panel component (sidebar beside deliberation)
- Proposed Memory Review modal/flow after session completes
- Per-item approve / edit / reject controls

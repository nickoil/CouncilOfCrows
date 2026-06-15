# Plan: Phase 5 — Subjects and Institutional Memory

## Context
Phase 5 introduces persistent institutional continuity to CouncilOfCrows. Currently every session is isolated — advisors have no awareness of prior deliberations. Phase 5 organises sessions into subject domains and injects episodic memory (prior questions, consensus, dissent, unresolved tensions) into advisor prompts, so the council develops genuine continuity across discussions on the same subject.

Phases 1–4 are complete. The backend is a Laravel 13 app; the frontend is SvelteKit 2 / Svelte 5. pgvector is already installed but not used yet (that is Phase 7).

---

## 1. Database Migrations (3 new)

### `create_subjects_table`
```
id, name (string, unique), slug (string, unique), description (text nullable), timestamps
```

### `add_subject_id_to_board_sessions`
Nullable FK → subjects.id. `onDelete('set null')`.

### `create_memory_items_table`
```
id
subject_id (FK → subjects)
board_session_id (FK → board_sessions)
advisor_id (FK → advisors, nullable)
type (enum: question | consensus | dissent | unresolved_question)
content (text)
metadata (json, nullable)   -- stores round, tension_key, etc.
timestamps
```

---

## 2. Models

**`Subject.php`** — fillable: name, slug, description. Relations: hasMany(BoardSession), hasMany(MemoryItem).

**`MemoryItem.php`** — fillable: all columns. Relations: belongsTo(Subject), belongsTo(BoardSession), belongsTo(Advisor).

**`BoardSession.php`** — add `subject_id` to fillable; add cast to integer; add `belongsTo(Subject)`.

---

## 3. MemoryBuilder Service

New file: `app/Services/MemoryBuilder.php`

Called at the end of `FinalizeCouncilDeliberation.php` (after `Orchestrator::finalize()` succeeds), only when the session has a `subject_id`.

Extracts and persists memory items from the just-completed session:

| Source | type |
|---|---|
| session.question | `question` |
| chair_summary response content | `consensus` |
| critique responses per tension | `dissent` (one per response, metadata stores tension_key) |
| selected_tensions not fully resolved by consensus | `unresolved_question` |

Keep it simple: plain-text content, no embeddings yet (that is Phase 7).

---

## 4. Orchestrator — Memory Injection

In `app/Services/Orchestrator.php`, add a private method:

```php
private function buildMemoryContext(int $subjectId, string $currentQuestion): string
```

Retrieves the last 5 completed sessions for the subject (excluding the current one), ordered by recency. For each, formats a brief block:

```
[PRIOR DISCUSSION — {date}]
Question: ...
Consensus: ...
Unresolved: ...
```

Returns a formatted string, or empty string if none.

Inject this context into:
- `buildSynthesisPrompt()` — prepend after question, before advisor outputs
- `handleIndependentAdvisor()` — prepend to the user-turn message before the question

Include a `--- Drawing on institutional memory ---` header so the frontend can detect when memory was used (check if any response references it, or pass a flag on the session).

Add a boolean column `memory_injected` (default false) to `board_sessions` migration so the UI can show the indicator without parsing response text.

---

## 5. API — Subjects & Updated Endpoints

### New routes in `routes/api.php`
```
GET  /api/subjects                     → SubjectsController@index
POST /api/subjects                     → SubjectsController@store
GET  /api/subjects/{subject}/sessions  → SubjectsController@sessions
```

### `SubjectsController.php`
- `index()`: return all subjects (id, name, slug, description, sessions_count)
- `store(Request)`: validate name (required, max 100), auto-generate slug, create Subject
- `sessions(Subject)`: return last 20 sessions for this subject via SessionPresenter

### Update `AskController.store()`
Accept optional `subject_id` (integer, exists:subjects,id). Store on BoardSession.

### Update `SessionPresenter.php`
Include `subject` (id, name) and `memory_injected` in the session payload.

---

## 6. Frontend

### `src/lib/helpers/api.js`
Add:
- `getSubjects()` — GET /api/subjects
- `createSubject(name)` — POST /api/subjects
- `getSubjectSessions(subjectId)` — GET /api/subjects/{id}/sessions

Update `ask(question, deliberationMode, subjectId = null)` to pass `subject_id`.

### New components

**`src/lib/components/SubjectSelector.svelte`**
Dropdown of existing subjects + "New subject…" option (inline text input). Emits `select` event with subject id (or null for no subject). Used inside the existing AskForm.

**`src/lib/components/SessionTranscript.svelte`**
Renders a full deliberation transcript for a session: collapsible Round 1 (independent), Round 2 (critiques, grouped by tension), Round 3 (chair synthesis). Shows `memory_injected` indicator ("Drawing on prior discussions") when true.

### New pages

**`src/routes/subjects/[id]/+page.svelte`**
Subject history page. Loads subject sessions. Lists sessions as cards: question, status, date, link to detail. Header shows subject name.

**`src/routes/sessions/[id]/+page.svelte`**
Session detail page. Loads full session via `getSession(id)`. Renders `SessionTranscript` component. Breadcrumb links back to subject if present.

### Updates to existing pages

**`src/routes/+page.svelte`**
Add `SubjectSelector` to the ask form. Pass selected subject id to `ask()`.

**`src/lib/components/SessionList.svelte`** (existing)
Add subject name badge on each session card. Link to `/sessions/[id]` for detail view.

---

## 7. Implementation Order

1. Migrations → models
2. MemoryBuilder service
3. Update FinalizeCouncilDeliberation + Orchestrator
4. SubjectsController + routes + AskController update + SessionPresenter update
5. Frontend api.js additions
6. SubjectSelector component + wire into ask form
7. SessionTranscript component
8. Sessions detail page
9. Subject history page
10. SessionList badge + links

---

## Critical Files to Modify

| File | Change |
|---|---|
| `backend/database/migrations/` | 3 new migration files |
| `backend/app/Models/BoardSession.php` | add subject_id, memory_injected, relation |
| `backend/app/Services/Orchestrator.php` | buildMemoryContext(), inject into prompts |
| `backend/app/Jobs/FinalizeCouncilDeliberation.php` | call MemoryBuilder after finalize() |
| `backend/app/Http/Controllers/Api/AskController.php` | accept subject_id |
| `backend/app/Http/Controllers/Api/SessionsController.php` | include subject in response |
| `backend/app/Services/SessionPresenter.php` | include subject + memory_injected |
| `backend/routes/api.php` | new subject routes |
| `frontend/src/lib/helpers/api.js` | getSubjects, createSubject, getSubjectSessions, update ask() |
| `frontend/src/routes/+page.svelte` | add SubjectSelector |

## New Files

| File | Purpose |
|---|---|
| `backend/app/Models/Subject.php` | Subject model |
| `backend/app/Models/MemoryItem.php` | MemoryItem model |
| `backend/app/Services/MemoryBuilder.php` | Extract + persist memory from completed sessions |
| `backend/app/Http/Controllers/Api/SubjectsController.php` | Subject CRUD + sessions |
| `frontend/src/lib/components/SubjectSelector.svelte` | Subject picker for ask form |
| `frontend/src/lib/components/SessionTranscript.svelte` | Full deliberation transcript view |
| `frontend/src/routes/subjects/[id]/+page.svelte` | Subject history page |
| `frontend/src/routes/sessions/[id]/+page.svelte` | Session detail page |

---

## Verification

1. Create a subject via POST /api/subjects
2. Submit two questions against that subject
3. Confirm second session's advisor prompts contain the first session's memory context (check advisor_responses content or add logging)
4. Confirm `memory_injected = true` on the second session
5. Open `/subjects/{id}` — should list both sessions
6. Open `/sessions/{id}` — full transcript with collapsible rounds, "Drawing on prior discussions" badge on session 2
7. Submit a question with no subject — confirm no memory injection, no regression in existing flow

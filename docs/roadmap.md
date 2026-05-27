# CouncilOfCrows — Deveopment Roadmap

Nick Casey 2026-05-24

This roadmap evolves the original implementation plan to align with the
architectural philosophy described in this document while preserving the
project’s deliberately incremental and operationally realistic build
sequence.

The intention is to:
- ship working systems early
- preserve observability and interpretability
- introduce complexity gradually
- maintain bounded deliberation and token discipline
- prioritise continuity and institutional memory over autonomous
  behaviour

Each phase should produce a stable and demonstrable system before
additional architectural layers are introduced.

## Phase 1 — Foundation

### Goal
Docker stack running, one LLM call functioning, response persisted.

The objective of this phase is architectural grounding rather than
sophistication.

### Infrastructure

#### Stack

- Docker Compose
- PostgreSQL (pgvector image)
- Redis
- Laravel backend
- SvelteKit frontend
- Nginx reverse proxy

#### Database

- enable pgvector extension via migration
- create:
  - sessions
  - advisors
  - advisor_responses

#### Model Access

- configure openai-php/client
- route requests through OpenRouter
- initial support for multiple providers

### API

**Initial Endpoint**

- /api/ask
- one question
- one model response
- persisted to database

### UI

- text input
- submit button
- displayed response
- session persistence

### Done When

A user can submit a question, receive a response, and retrieve the
stored session from PostgreSQL.

## Phase 2 — Multiple Advisors, One Round

### Goal

Introduce a persistent council structure with multiple independent
advisor responses.

The objective is to establish bounded deliberation without critique or
recursive interaction.

### Advisors

**Seed Initial Roles**

Examples:
- strategist
- sceptic
- synthesiser
- technical reviewer
- creative alternative generator

Roles should remain cognitive rather than domain-specific.

### Orchestration

**Introduce Orchestrator**

The orchestrator:
- receives the question
- selects participating advisors
- dispatches advisor jobs
- enforces token and participation limits
- aggregates results

**Job Execution**

- one CallAdvisorJob per advisor
- dispatch as batch
- Bus::batch() used for completion detection

### Deliberation Model

**Initial Structure**

User Question 
→
Orchestrator
→
Independent Advisor Responses
→
Consensus Synthesis

**Constraints**

- single deliberation round only
- fixed advisor count
- strict token budgets
- no advisor-to-advisor interaction yet

### UI

- advisor cards
- independent responses visible separately
- consensus displayed distinctly

### Done When

One question produces multiple independent advisor responses and a
synthesised consensus.

## Phase 3 — Real-Time Deliberation UI

### Goal

Expose deliberation as a visible institutional process rather than a hidden batch operation.

### Infrastructure

**Realtime Layer**

- Laravel Reverb
- WebSocket broadcasting
- live session channels

### Behaviour

- advisor responses appear incrementally
- pending/completed states visible
- synthesis appears separately

### UX Philosophy

This phase introduces:
- visible reasoning
- traceable participation
- transparent deliberation
rather than presenting the institution as a single opaque assistant.

### Constraints

- bounded response lengths
- bounded advisor count
- no uncontrolled conversational loops

### Done When

The user can observe deliberation unfolding live as advisors complete
their responses.

## Phase 4 — Structured Debate Rounds

### Goal

Move from parallel response generation toward explicit staged
deliberation.

### Deliberation Stages

**Round 1 — Independent Responses**

Advisors respond independently.

**Round 2 — Critique**

Advisors receive:
- other advisor responses
- targeted critique prompts
- disagreement focus areas

Critique should emphasise:
- assumptions
- contradictions
- uncertainty
- omitted considerations
- hallucination risk

### Final Synthesis

The chairperson/orchestrator produces:
- consensus summary
- disagreement summary
- unresolved questions
- confidence indicators

### Architecture

**Job Chaining**

- Round 1 batch
- Round 2 critique batch
- synthesis job

### New Persistence

Store:
- critique rounds
- revisions
- dissent
- synthesis history

### UI

- round separators
- visible critiques
- disagreement indicators
- synthesis timeline

### Constraints

- bounded critique rounds
- no unrestricted advisor recursion
- hard token ceilings per phase

### Done When

The institution performs visible multi-stage deliberation with critique and synthesis.

## Phase 5 — Subjects and Institutional Memory

### Goal

Introduce persistent institutional continuity organised around subject domains.

### Subject Organisation

- subjects table
- subject selector
- subject-scoped sessions

### Views

- subject history page
- session detail page
- full deliberation transcript
- collapsible rounds

### Episodic Memory

Store:
- questions
- advisor participation
- critiques
- consensus
- dissent
- timestamps

The institution should now begin developing historical continuity rather than isolated conversations.

### Initial Retrieval

- retrieve prior sessions by subject
- inject summaries into advisor prompts
- preserve explicit memory references

### UI

- visible historical references
- linked prior deliberations
- “drawing on previous discussions” indicators

### Done When

The council demonstrates visible continuity between discussions within a subject area.

## Phase 6 — Cost Tracking and Deliberative Constraints

### Goal

Prevent runaway token growth and maintain bounded institutional reasoning.

### Cost Tracking

Track:
- prompt tokens
- completion tokens
- cost per response
- cost per session
- monthly cost totals

### Deliberative Constraints

**Governance**

The orchestrator should enforce:
- maximum advisor count
- maximum deliberation rounds
- retrieval limits
- specialist limits
- session token budgets

### Graceful Degradation

If budgets are exceeded:
- reduce advisor participation
- shorten critique depth
- compress retrieval context
- return partial consensus with explicit limitations

### UI

- visible session costs
- estimated deliberation cost
- warning thresholds

### Done When

Every deliberation has measurable and bounded operational cost.

## Phase 7 — Semantic Memory

### Goal

Allow the institution to consolidate repeated deliberations into higher-level understanding.

### Semantic Memory

**Introduce**

- embeddings pipeline
- semantic retrieval
- thematic extraction
- memory consolidation

### Embeddings

**Suggested Approach**

- memory_embeddings table
- pgvector similarity search
- Ollama local embeddings
- nomic-embed-text

### Consolidation

Rather than storing only raw transcripts, the institution should begin
extracting:
- recurring themes
- persistent risks
- stable preferences
- conceptual relationships
- evolving conclusions
- unresolved tensions

### Retrieval Behaviour

Before each deliberation:
- retrieve semantically related prior discussions
- inject distilled institutional summaries
- preserve visible memory references

### UX

- visible semantic memory references
- retrieved memory cards
- historical continuity indicators

### Done When

The institution demonstrates accumulated contextual understanding across repeated discussions.

**Nick Note**: read more on semantic retrieval

## Phase 8 — Depth Modes and Specialist Participation

### Goal

Allow users to consciously trade off deliberative depth, cost, and expertise.

### Depth Modes

**Quick**

- one response round
- cheap models
- fast synthesis

**Standard**

- critique round
- moderate retrieval
- standard synthesis

**Deep**

- multiple critique rounds
- premium models
- expanded retrieval
- specialist participation

### Dynamic Specialists

**Specialist Model**

Specialists:
- are temporary
- are tightly scoped
- receive constrained context
- may use specialised tools
- do not persist institutionally

**Specialist Triggers**

- high disagreement
- low confidence
- specialist domain requirement
- explicit user request

### UI

- mode selector
- cost/time estimates
- specialist participation indicators
- explicit deep-mode confirmation

### Constraints

- bounded specialist lifespan
- token-restricted outputs
- orchestrator-controlled spawning

### Done When

Users can deliberately control reasoning depth and temporary expertise
participation.


## Phase 9 — Epistemic Tracking and Hallucination Mitigation

### Goal

Make uncertainty, disagreement, and historical reasoning quality visible institutional properties.

### Structured Outputs

Require:
- confidence estimates
- uncertainty declarations
- supporting assumptions
- evidence references

### Deliberation Behaviour

The orchestrator should:
- detect disagreement patterns
- escalate critique where needed
- preserve unresolved tensions
- avoid false consensus generation

### Tracking

Store:
- advisor confidence histories
- disagreement frequency
- prediction outcomes
- revision patterns
- historical reliability indicators

### UI

- confidence badges
- disagreement warnings
- revision timelines
- epistemic traceability indicators

### Done When

Disagreement, uncertainty, and historical reasoning quality become visible institutional features.

---

## Phase 10 — Human Feedback and Institutional Governance

### Goal

Integrate human participants into the institution’s epistemic development.

The user should function not merely as a prompt source, but as an ongoing institutional participant.

### Feedback Types

**Outcome Feedback**

Users may report:
- success
- failure
- changed circumstances
- real-world consequences

**Reasoning Critique**

Users may identify:
- flawed assumptions
- weak synthesis
- ignored constraints
- strategic blind spots

**Preference Correction**

Users may refine:
- priorities
- trade-offs
- stylistic preferences
- operational constraints

**Memory Curation**

Users may:
- preserve conclusions
- archive exploratory discussions
- flag weak deliberations
- retain unresolved disagreement

### Governance

Human approval required for:
- advisor role changes
- orchestration modifications
- memory deletion
- specialist permissions
- institutional constraint changes

### UI

- feedback controls
- institutional memory management
- governance dashboard
- advisor performance views

### Done When

Human feedback becomes part of institutional memory and long-term
epistemic development.

## Closing Principle

The roadmap intentionally prioritises gradual capability expansion over
premature complexity. Each phase should produce a stable, observable,
and inspectable deliberative system before additional cognitive
mechanisms are introduced.

The objective is not to maximise apparent intelligence through scale or
autonomy, but to explore whether persistent institutional reasoning can
produce more reliable, transparent, and historically grounded forms of
machine-assisted deliberation.

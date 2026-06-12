# Project: CouncilOfCrows

## Stack
Laravel 13 (PHP 8.3), SvelteKit 2 / Svelte 5 (TypeScript), PostgreSQL 16 + pgvector, Redis 7, Laravel Reverb (WebSockets), Docker Compose (Nginx + 5 queue workers), OpenAI PHP client

## Directories to ignore
Do not read or search in the following directories unless I explicitly ask:
- node_modules/
- vendor/
- build/
- dist/
- .next/
- coverage/
- .cache/

## Current Phase
Phase 6: Cost Tracking and Deliberative Constraints — Phases 1–5 complete

## Architecture Decisions
- Councils over swarms: advisors are persistent with identity and memory, not ephemeral agents
- Cognitive roles, not subject silos: advisors are defined by reasoning style (skeptic, synthesizer, etc.)
- Deliberation is staged: independent response → critique → revision → synthesis → dissent preserved
- Bounded deliberation: token discipline is a first-class design constraint, not an afterthought
- Two-layer memory: episodic (raw deliberation history) + semantic (consolidated understanding via embeddings)
- Advisor parallelism via job queue: each advisor runs as a dispatched Laravel job picked up by workers

## What Claude Should Know
- Advisor concurrency flows through the queue — don't bypass jobs with direct calls
- pgvector is already installed (will be used in Phase 7 for semantic memory)
- WebSocket events broadcast through Laravel Reverb; frontend subscribes via Laravel Echo
- Non-goal: AGI, autonomous employees, or self-modifying behavior — this is a deliberation research tool
- The staged deliberation flow is intentional architecture, not an optimisation target

## Phase History
See [docs/phase-history.md](docs/phase-history.md)


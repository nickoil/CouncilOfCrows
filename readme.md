# CouncilOfCrows

Persistent institutional deliberation for large language model systems.

CouncilOfCrows explores whether multiple collaborating LLMs, organised as a structured advisory council with persistent memory and transparent deliberation, can produce more reliable and historically grounded reasoning than isolated prompt-response systems.


The project focuses on:
- persistent institutional memory
- structured deliberation
- disagreement preservation
- transparent consensus
- bounded multi-agent orchestration
- longitudinal reasoning continuity

Rather than treating LLMs as isolated assistants or autonomous swarms, the system explores a model of persistent collaborative cognition coordinated through a central orchestration layer.

## Current Status

Early-stage architecture and prototype development.

Current focus:
- Laravel orchestration layer
- OpenRouter model integration
- persistent session storage
- advisor orchestration
- structured deliberation

## Development Notes

The Docker Compose stack currently defines a fixed five-worker Laravel queue pool:
- `worker`
- `worker-2`
- `worker-3`
- `worker-4`
- `worker-5`

This is the pool that enables advisor-level parallelism for a single session. To change deliberation concurrency in development, edit the worker service count in [docker-compose.yml](docker-compose.yml) rather than relying on an ad hoc `docker compose up --scale ...` command.

## Documents

- `/docs/foundation.md` — architectural philosophy and system model
- `/docs/roadmap.md` — staged implementation roadmap

## Principles

CouncilOfCrows is intentionally:
- not artificial general intelligence (AGI)
- not autonomous agents
- not unrestricted self-modification
- not infinite agent swarms

The project prioritises:
- interpretability
- bounded reasoning
- institutional continuity
- human oversight
- epistemic traceability

### Important Note (and writing without ChatGPT!). 

The basic idea for this project came to me late last year (2025) - a system where LLMs from different providers could argue amongst themselves to come up with a concensus. I had assumed it had been thought of - without much research GraphLang seemed to have been built to do exactly that - and all I was going to be doing is implementing some details. 

When I took it to an AI - ChatGPT in this case - it very quickly persuaded me that this was a piece of original thought and together we drew up the [foundation](docs/foundation.md), in order to create some kind of academic buzz around it. This kept me out of the sun on a bank holiday which is probably a good thing.

What ChatGPT didnt mention is that [Andrej Kaparthy](https://en.wikipedia.org/wiki/Andrej_Karpathy), co-founder of the very organisation that birthed it, had thought of, and vibe coded in a weekend, [the very same thing](https://github.com/karpathy/llm-council). This was about the same time I thought of it - one lesson to be learned: move fast!

The lessons I always wanted to learn were those that would teach me more about LLMs and their workings - the areas concerning semantic memory and the requirements for embeddings for context have already been very useful as far as that is concerned. But I have also learned, or been thoroughly reminded, as silver-tongued as they are: **never trust an AI**!

And there is still some orginal thought here although it is philosphical rather than technical. While I started from the same position as Kaparthy

> A council is a mechanism for producing better answers.

I have wound up with something different

> A council is a persistent institution whose answers are 
only one manifestation of its ongoing development.

Through memory, my "institution" contains "permanent" members, and historical concensus and disagreement helps to improve answers to related questions and ingoing issues. Very importantly there are human members the institution!

Ironically, had I been building CouncilOfCrows with the help of CouncilOfCrows and a mixture of LLMs, it is less likely that I would have been led down any kind of garden path. Or at least I like to think so. I had better build it to find out ...

## Author

Nick Casey <nickoil@hotmail.com>
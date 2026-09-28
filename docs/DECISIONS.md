# Architecture Decisions

This file records important decisions and their rationale.

## ADR-001 — Backend-first REST API

Status: Accepted

Decision:
Build the initial HelpDesk as a REST API.

Reason:
The current learning focus is backend engineering with PHP 8.5 and Laravel 13.

Frontend options such as Blade, React, and Vue are deferred.

## ADR-002 — Single tenant first

Status: Accepted

Decision:
Build a normal single-tenant HelpDesk first.

Reason:
Learn the domain and Laravel fundamentals before introducing multi-tenancy complexity.

Future:
Convert the application into a multi-tenant SaaS.

## ADR-003 — MySQL

Status: Accepted

Decision:
Use MySQL 8.4 LTS.

Reason:
It matches the original stack choice and is fully appropriate for the relational HelpDesk workload.

PostgreSQL may be explored separately later.

## ADR-004 — Redis

Status: Accepted

Decision:
Use Redis for queues, caching, and appropriate distributed/concurrency coordination.

Reason:
The project should provide practical Redis experience rather than using Redis only as infrastructure decoration.

## ADR-005 — Ticket status history and audit log

Status: Accepted

Decision:
Use both concepts.

Reason:
Ticket status history is a domain-specific lifecycle record, while audit logs provide broader change tracking.

## ADR-006 — Dedicated transactional idempotency subsystem

Status: Accepted

Decision:
Use a dedicated `idempotency_keys` table and reusable `IdempotencyManager` instead of storing an idempotency key directly on `tickets`.

The uniqueness boundary is:

`scope_type + scope_id + operation + key_hash`

The raw client key is hashed before persistence. The validated business request is canonically serialized and hashed so a key cannot safely be reused with a different request payload.

The idempotency record and the business mutation are committed in the same MySQL transaction. The original response status/body are stored for deterministic replay.

Reason:
Ticket creation is only the first mutating API operation that benefits from idempotency. A dedicated subsystem provides a reusable, auditable, secure, and future multi-tenant-aware boundary without making Redis the transactional source of truth.

Concurrency is enforced by the database unique constraint. Redis may be used later as an optimization or coordination aid, but correctness does not depend on it.

Limitation:
This provides strong idempotency for transactional database mutations. It is not an exactly-once guarantee for arbitrary external side effects. Those require an outbox/event architecture.

## ADR-007 — Design patterns

Status: Accepted

Decision:
Practice Strategy, Factory, State, Chain of Responsibility, Command/Action, Adapter, Decorator, and Events/Observer where justified.

Reason:
The project is also intended as a practical design-pattern refresher.

## ADR-008 — Laravel Boost

Status: Accepted

Decision:
Laravel Boost may be used from day one as a development aid.

Reason:
It can provide project-aware assistance while the developer remains responsible for understanding and implementing the application.

## ADR-009 — Laravel AI SDK

Status: Planned

Decision:
Introduce Laravel AI SDK in a later phase.

Initial AI features may include:

- ticket classification
- ticket summarization
- suggested replies
- knowledge-base/RAG assistance

Reason:
The core HelpDesk should be implemented and understood before adding AI features.

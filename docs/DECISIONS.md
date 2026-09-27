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

## ADR-006 — Ticket creation idempotency

Status: Accepted

Decision:
Support an idempotency key for ticket creation.

Current approach:
Use a unique idempotency_key on tickets.

Future:
Consider a dedicated idempotency_keys table if idempotency is required for multiple API operations.

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

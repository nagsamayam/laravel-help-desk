# Architecture Decisions Records (ADRs)

This document records the key architectural decisions, context, and consequences for the HelpDesk project.

---

## ADR-001 — REST API & React 19 Frontend
- **Status:** Accepted
- **Decision:** Build a decoupled backend REST API (`/api/v1`) paired with a modern React 19 Single Page Application (SPA) styled with Tailwind CSS v4 and shadcn/ui.
- **Reason:** Provides clean separation between backend domain business logic and responsive, accessible client UI experiences.

---

## ADR-002 — Single-Tenant First with Clean Multi-Tenant Path
- **Status:** Accepted
- **Decision:** Build a single-tenant application first, while structuring database schemas and idempotency namespaces with `scope_type` and `scope_id` for seamless multi-tenant migration.
- **Reason:** Focus on domain mastery and clean architecture without premature SaaS complexity.

---

## ADR-003 — MySQL 8.4 LTS as Authoritative Source of Truth
- **Status:** Accepted
- **Decision:** Use MySQL 8.4 LTS with InnoDB engine and ACID transactions as the single source of truth.
- **Reason:** Ensures data integrity, foreign key consistency, and atomic uniqueness constraints for idempotency.

---

## ADR-004 — Redis for Caching, Distributed Locks, and Job Queues
- **Status:** Accepted
- **Decision:** Utilize Redis for fast idempotency replay caching, distributed concurrency locks, and background queue workers (`notifications`, `default`, `maintenance`).
- **Reason:** Provides high-throughput asynchronous execution and reduces database contention.

---

## ADR-005 — Separation of Status History and Model Audit Logs
- **Status:** Accepted
- **Decision:** Maintain both `ticket_status_histories` (domain-specific state progression timeline) and `audit_logs` (generic mutation tracking with JSON diffs).
- **Reason:** Status history answers *lifecycle progression questions* (from, to, reason, agent), while audit logs answer *security and compliance questions* (who changed what attribute, IP address, user agent).

---

## ADR-006 — Database-Authoritative Transactional Idempotency Subsystem
- **Status:** Accepted
- **Decision:** Implement idempotency using a dedicated `idempotency_keys` table and `IdempotencyManager` where uniqueness is enforced by MySQL composite unique constraints: `(scope_type, scope_id, operation, key_hash)`.
- **Reason:** Eliminates race conditions and avoids reliance on Redis as a transactional source of truth. Concurrent in-flight collisions return HTTP 409 with `Retry-After: 2` (`IdempotencyInFlightException`).

---

## ADR-007 — Application of Enterprise Software Design Patterns
- **Status:** Accepted
- **Decision:** Implement State, Strategy, Chain of Responsibility, Command/Action, Decorator, Events/Observer, and Specification patterns where they directly solve architectural needs.
- **Reason:** Encapsulates domain invariants, decouples business rules, and ensures maintainable, testable code.

---

## ADR-008 — Domain-Driven Design (DDD) Bounded Contexts
- **Status:** Accepted
- **Decision:** Refactor the codebase into bounded contexts (`Domain/Ticket`, `Domain/Identity`, `Domain/Audit`), decoupled infrastructure (`Infrastructure/Idempotency`, `Infrastructure/Notifications`), and thin presentation layers (`Http/`).
- **Reason:** Prevents monolithic codebase rot and establishes clear ownership of domain entities, actions, policies, and events.

---

## ADR-009 — Modular Domain Service Providers
- **Status:** Accepted
- **Decision:** Use domain-specific service providers (`TicketServiceProvider`, `TicketEventServiceProvider`, `IdentityServiceProvider`, `AuditServiceProvider`, `NotificationServiceProvider`) rather than centralizing all registrations in `AppServiceProvider`.
- **Reason:** Adheres to DDD principles, improves modularity, and avoids service provider bloat.

---

## ADR-010 — IdempotentRequest Route Middleware
- **Status:** Accepted
- **Decision:** Extract HTTP idempotency handling to an `IdempotentRequest` middleware that inspects `Idempotency-Key` headers, handles request canonicalization, executes mutating actions through `IdempotencyManager`, and captures resource metadata (`resource_type`, `resource_id`).
- **Reason:** Decouples API controllers from repetitive idempotency orchestration while ensuring consistent replay headers across all endpoints.

---

## ADR-011 — Transactional Blade Mailables & Decorator Notifications
- **Status:** Accepted
- **Decision:** Implement transactional emails with `TicketNotificationMail` Blade templates and route outbound emails through a decorated pipeline (`Logging` → `Metrics` → `Retry` → `EmailNotificationSender`).
- **Reason:** Delivers beautifully formatted emails to customers and staff while isolating internal staff notes from public customer feeds.

---

## ADR-012 — Asynchronous Queues with Job Uniqueness & Concurrency Locks
- **Status:** Accepted
- **Decision:** Execute notification listeners and maintenance jobs asynchronously via Redis queues, leveraging `ShouldBeUnique`, `WithoutOverlapping`, `ThrottlesExceptions`, and `RateLimited` middleware.
- **Reason:** Decouples heavy I/O from HTTP responses and guarantees safe background concurrency.

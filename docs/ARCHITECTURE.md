# System Architecture

## Architecture Overview

The HelpDesk platform is built as a modular, production-ready system utilizing **Laravel 11/12** on the backend and a **React 19 Single Page Application (SPA)** on the frontend.

```text
                               ┌────────────────────────────────────────┐
                               │   React 19 SPA (Vite + Tailwind v4)    │
                               │  TanStack Query v5 + Zustand + shadcn  │
                               └───────────────────┬────────────────────┘
                                                   │ HTTP / REST API (JSON)
                                                   ▼
┌─────────────────────────────────────────────────────────────────────────────────────────────────┐
│                                 Laravel Application Core                                        │
│                                                                                                 │
│  ┌───────────────────────────────── HTTP Delivery Layer ─────────────────────────────────────┐  │
│  │ Routes (/api/v1) • Controllers • FormRequests • API Resources • IdempotentRequest MW     │  │
│  └───────────────────────────────────────────────┬────────────────────────────────────────────┘  │
│                                                  ▼                                              │
│  ┌────────────────────────────────── Domain Layer (DDD) ────────────────────────────────────┐  │
│  │                                                                                           │  │
│  │  Domain/Ticket Context             Domain/Identity Context        Domain/Audit Context     │  │
│  │  ├── Aggregate Root & Entities     ├── User Model                 ├── AuditLog Model       │  │
│  │  ├── State Pattern Transitions     ├── Role Enum (Admin, Agent,   ├── AuditLogger Service  │  │
│  │  ├── Assignment Strategies         │   Customer)                  └── Audit Policies       │  │
│  │  ├── Routing Chain of Resp.        └── User Policies                                       │  │
│  │  ├── Command / Action Classes                                                              │  │
│  │  ├── Specification Query Objects                                                           │  │
│  │  ├── Domain Events & Observers                                                             │  │
│  │  └── Asynchronous Domain Jobs                                                              │  │
│  └───────────────────────────────────────────────┬────────────────────────────────────────────┘  │
│                                                  ▼                                              │
│  ┌──────────────────────────────── Infrastructure Services ─────────────────────────────────┐  │
│  │                                                                                           │  │
│  │  Infrastructure/Idempotency               Infrastructure/Notifications                    │  │
│  │  ├── IdempotencyManager                   ├── NotificationSenderInterface (Contract)      │  │
│  │  ├── RedisIdempotencyStore (Locks/Cache)  ├── EmailNotificationSender (Laravel Mail)      │  │
│  │  └── IdempotencyKey Model & Pruning       └── Decorator Pipeline (Logging, Metrics, Retry)│  │
│  └───────────────────────────────────────────────────────────────────────────────────────────┘  │
└─────────────────────────────────────────────┬───────────────────────────────────────────────────┘
                                              │
                       ┌──────────────────────┴──────────────────────┐
                       ▼                                             ▼
            ┌─────────────────────┐                       ┌─────────────────────┐
            │    MySQL 8.4 LTS    │                       │     Redis 8.x       │
            │   (Source of Truth) │                       │  (Locks, Cache,     │
            │  ACID Transactions  │                       │   Queues/Workers)   │
            └─────────────────────┘                       └─────────────────────┘
```

For a comprehensive guide on our Domain-Driven Design (DDD) organization, layered boundaries, and implementation patterns, see [DDD Architecture Guide](DDD_ARCHITECTURE_GUIDE.md).

---

## Architectural Principles & Layer Boundaries

### 1. HTTP Delivery / Presentation Layer (`app/Http/`)
- **Thin Controllers:** Controllers only coordinate transport concerns (validating input, checking policy authorization, invoking domain actions, and formatting JSON responses).
- **Form Requests (`app/Http/Requests/`):** Encapsulate HTTP payload validation, role authorization, and parameter extraction before domain execution.
- **IdempotentRequest Middleware:** Inspects `Idempotency-Key` headers, hashes request payloads, executes mutating requests through `IdempotencyManager`, and returns replayed responses with headers.
- **API Resources (`app/Http/Resources/`):** Transform domain models and value objects into deterministic, versioned JSON responses.

### 2. Domain Layer (`app/Domain/`)
Pure business logic isolated within explicit Bounded Contexts:
- **`Domain/Ticket/`:** Ticket aggregate roots, ticket messages, state machine transitions, assignment strategies, routing rules, specification query filters, and domain events.
- **`Domain/Identity/`:** Users, role definitions (`Admin`, `Agent`, `Customer`), and identity policies.
- **`Domain/Audit/`:** Audit logs, change tracking, and audit logging services.

Each domain context registers its own policies and event listeners via **Domain Service Providers** (`TicketServiceProvider`, `TicketEventServiceProvider`, `IdentityServiceProvider`, `AuditServiceProvider`).

### 3. Infrastructure Layer (`app/Infrastructure/`)
Technical mechanisms that support domain workflows:
- **`Infrastructure/Idempotency/`:** Manages distributed concurrency locks, replay caching, request fingerprinting, and transactional key persistence.
- **`Infrastructure/Notifications/`:** Provides the `NotificationSenderInterface` contract, mail transport via `EmailNotificationSender`, and the decorator chain (`LoggingNotificationSenderDecorator`, `MetricsNotificationSenderDecorator`, `RetryNotificationSenderDecorator`).
- **`Infrastructure/Broadcasting/`:** Real-time WebSocket event dispatching with Laravel Reverb, Redis queue transport, and JWT channel authorization. See [Real-Time Broadcasting Subsystem](REVERB_BROADCASTING.md).
- **`Infrastructure/Storage/` (Attachments):** Multi-part chunked streaming uploader, local filesystem persistence, and AWS S3 pre-signed URL generation. See [Ticket Attachment Subsystem](TICKET_ATTACHMENTS.md).

### 4. Background Queues & Asynchronous Workers
- **Redis Queue Engine:** Decouples expensive operations (emails, background SLA escalations, auto-closures) from the HTTP request cycle.
- **Transactional Dispatching:** Events implement `ShouldDispatchAfterCommit` to prevent dispatching queued jobs if the surrounding database transaction rolls back.
- **Job Reliability:** Jobs implement `ShouldBeUnique`, `WithoutOverlapping` concurrency locks, exception throttling (`ThrottlesExceptions`), and queue rate limiting (`RateLimited`).

---

## Data Flow: Idempotent Ticket Creation

```text
Client (React SPA)
      │  POST /api/v1/tickets
      │  Header: Idempotency-Key: <UUIDv4>
      ▼
IdempotentRequest Middleware
      │
      ├── 1. Canonicalize Request & Generate SHA-256 Hash
      │
      ├── 2. Check Redis / DB Idempotency Store
      │         ├── If completed record exists & hashes match:
      │         │     └── Return Cached Response (HTTP 201, Idempotency-Replayed: true)
      │         └── If in-flight lock exists:
      │               └── Throw IdempotencyInFlightException (HTTP 409, Retry-After: 2)
      │
      └── 3. Execute Inside IdempotencyManager DB Transaction
                │
                ├── CreateTicketAction::execute(CreateTicketData)
                │         │
                │         ├── Inserts Ticket into MySQL
                │         └── Dispatches TicketCreated Domain Event
                │
                ├── Persist IdempotencyKey (key_hash, request_hash, response_body, resource metadata)
                │
                └── DB Commit
                          │
                          ├── [After Commit] SendTicketNotificationListener (Queued on Redis)
                          └── Returns Fresh Response (HTTP 201, Idempotency-Replayed: false)
```

---

## Future Multi-Tenancy Architecture

The system is designed to seamlessly scale into a multi-tenant SaaS application:
1. **Database Multi-Tenancy:** Single-database with `tenant_id` columns across domain models and Global Scopes.
2. **Tenant Scoping for Idempotency:** The composite unique key in `idempotency_keys` already uses `scope_type` and `scope_id`, easily switching from `'user'` to `'tenant'`.
3. **Queue & Cache Partitioning:** Tenant-scoped Redis cache prefixes and queue routing tags.

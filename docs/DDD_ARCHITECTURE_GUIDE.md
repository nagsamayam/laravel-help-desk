# Domain-Driven Design (DDD) & Layered Architecture Guide

This document outlines the Domain-Driven Design (DDD) organization, layered boundaries, design principles, and guidelines for adding future components (commands, jobs, events) to the Laravel Help Desk application.

---

## 1. Directory Structure & Layer Overview

The backend is structured into concentric architectural layers:

```text
app/
├── Domain/                   # Pure business logic and domain rules (Core)
│   ├── Ticket/               # Ticket Bounded Context
│   │   ├── Actions/          # Commands / use-case actions (CreateTicketAction, ResolveTicketAction, etc.)
│   │   ├── DTOs/             # Immutable data transfer objects (CreateTicketData, UpdateTicketData)
│   │   ├── Enums/            # Domain enums (TicketStatus, TicketPriority)
│   │   ├── Events/           # Domain lifecycle events (TicketCreated, TicketStatusChanged, etc.)
│   │   ├── Exceptions/       # Domain rule violations (InvalidTicketStateTransitionException)
│   │   ├── Listeners/        # Domain event listeners (LogTicketActivityListener, etc.)
│   │   ├── Models/           # Eloquent Aggregate Roots & Entities (Ticket, Category, TicketMessage, TicketStatusHistory)
│   │   ├── Observers/        # Model lifecycle observers (TicketObserver)
│   │   ├── Policies/         # Domain authorization rules (TicketPolicy, CategoryPolicy)
│   │   ├── Routing/          # Chain of Responsibility routing pipeline & rules
│   │   ├── Services/         # Domain services (TicketAssignmentService)
│   │   ├── Specifications/   # Specification pattern query objects (UrgentTicketSpecification, OverdueTicketSpecification, etc.)
│   │   ├── States/           # State pattern state machines (OpenTicketState, InProgressTicketState, etc.)
│   │   └── Strategies/       # Strategy pattern algorithms (RoundRobinAssignment, LeastBusyAgentAssignment, etc.)
│   ├── Identity/             # Identity & Access Management Bounded Context
│   │   ├── Enums/            # Role enum (ADMIN, AGENT, CUSTOMER)
│   │   ├── Models/           # User model
│   │   └── Policies/         # UserPolicy
│   └── Audit/                # Audit Trail Bounded Context
│       ├── Models/           # AuditLog model
│       ├── Policies/         # AuditLogPolicy
│       └── Services/         # AuditLogger service
│
├── Infrastructure/           # Technical capabilities, external drivers & cross-cutting tools
│   ├── Idempotency/          # Distributed locking, cache replay, hashing & key storage
│   │   ├── Exceptions/       # IdempotencyConflictException, IdempotencyInFlightException
│   │   ├── Models/           # IdempotencyKey model
│   │   ├── IdempotencyManager.php
│   │   ├── IdempotencyResult.php
│   │   └── RedisIdempotencyStore.php
│   └── Notifications/        # Notification dispatch, channels & decorators
│       ├── Contracts/        # NotificationSenderInterface
│       ├── Decorators/       # Logging, Metrics, and Retry decorators
│       ├── EmailNotificationSender.php
│       └── NotificationResult.php
│
├── Http/                     # Presentation / Delivery / Transport Layer (Web & REST API)
│   ├── Controllers/          # Transport entrypoints handling HTTP requests/responses
│   ├── Middleware/           # HTTP filters (IdempotentRequest, CheckRole)
│   ├── Requests/             # HTTP Form validation & input extraction
│   ├── Resources/            # JSON API representation / serialization
│   └── Responses/            # Standardized API response envelopes
```

---

## 2. Layer Responsibilities & Semantics

### Domain (`app/Domain/`)
- Represents the **problem space** (core business rules, invariants, and business processes).
- Contains models, state machines, strategies, actions/commands, domain events, domain exceptions, and specifications.
- **Rule:** Domain code does not depend on the HTTP transport layer (`Illuminate\Http\Request`, controllers, or API resources).

### Infrastructure (`app/Infrastructure/`)
- Represents the **technical mechanisms** and cross-cutting capabilities that support domain workflows.
- Handles external integrations: Redis caching, distributed concurrency locking, email dispatchers, external message brokers, and third-party APIs.
- **Swappability:** Changing from Redis to DynamoDB for idempotency or switching email providers only touches classes in `app/Infrastructure/`, leaving the domain untouched.

### HTTP / Delivery Layer (`app/Http/`)
- Represents the **transport/delivery mechanism**.
- Translates inbound HTTP requests into validated domain DTOs and passes them to domain Actions.
- Serializes domain model responses into standardized JSON via API Resources and `ApiResponse`.

---

## 3. Why FormRequests & Responses Remain in `Http/`

1. **Transport Isolation:** HTTP is one of several possible delivery methods (CLI commands, queue workers, webhooks, gRPC). Business logic inside `CreateTicketAction` accepts a typed `CreateTicketData` DTO instead of `Illuminate\Http\Request`.
2. **Reusability:** The exact same domain Action can be invoked by a REST controller, an Artisan command, or an automated email parser without mocking HTTP request objects.
3. **Serialization Decoupling:** API transformations in `Http/Resources/` ensure changes to the external REST schema do not leak into internal domain state.

---

## 4. Exception Organization

- **Domain Exceptions (`app/Domain/{Context}/Exceptions/`):** Business rule and invariant violations (e.g., `InvalidTicketStateTransitionException`).
- **Infrastructure Exceptions (`app/Infrastructure/{Context}/Exceptions/`):** Technical failures and concurrency collisions (e.g., `IdempotencyConflictException`, `IdempotencyInFlightException`).
- **Framework & Global Exceptions:** Standard Laravel/Symfony exceptions (`AuthenticationException`, `AuthorizationException`, `ModelNotFoundException`) are handled and mapped to uniform JSON responses in `bootstrap/app.php`.

---

## 5. Adding Scheduled Tasks, Commands & Background Jobs

### Scheduled Commands (`routes/console.php` & `app/Domain/{Context}/Commands/`)
In Laravel 11/12, scheduled tasks are configured directly in `routes/console.php` using the `Schedule` facade:

```php
// routes/console.php
use Illuminate\Support\Facades\Schedule;
use App\Infrastructure\Idempotency\Models\IdempotencyKey;
use App\Domain\Ticket\Models\Ticket;
use App\Domain\Ticket\Specifications\OverdueTicketSpecification;

// Maintenance: Prune expired idempotency keys daily
Schedule::command('model:prune', [
    '--model' => [IdempotencyKey::class],
])->daily();

// Domain Scheduled Task: Check overdue tickets every 15 minutes
Schedule::call(function () {
    $overdueTickets = Ticket::matching(new OverdueTicketSpecification())->get();
    foreach ($overdueTickets as $ticket) {
        // dispatch domain actions or alert jobs
    }
})->everyFifteenMinutes();
```

### Background Queue Jobs (`app/Domain/{Context}/Jobs/`)
- Place asynchronous jobs that execute business logic (e.g., `SendOverdueTicketReminderJob`, `ProcessTicketRoutingJob`) inside `app/Domain/{Context}/Jobs/`.
- Domain jobs should invoke domain Action classes (`CreateTicketAction`, `ResolveTicketAction`, `AssignTicketAction`) to maintain uniform business logic and event triggering.

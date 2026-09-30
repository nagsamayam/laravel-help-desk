# HelpDesk

A modern, production-grade HelpDesk application built with **PHP 8.2+ / Laravel 13**, **MySQL 8.4 LTS**, **Redis**, and a **React 19 Single Page Application (SPA)** styled with **Tailwind CSS v4** and **shadcn/ui** components.

The project demonstrates Domain-Driven Design (DDD), battle-tested software design patterns, resilient transactional idempotency, background job queues, multi-stakeholder email notifications, and comprehensive test coverage.

---

## Architecture Overview

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

---

## Key Features

### 1. Robust Domain-Driven Architecture (DDD)
- **Bounded Contexts:** Clear separation between `Domain/Ticket`, `Domain/Identity`, and `Domain/Audit`.
- **Domain Service Providers:** Modular providers (`TicketServiceProvider`, `TicketEventServiceProvider`, `IdentityServiceProvider`, `AuditServiceProvider`, `NotificationServiceProvider`) maintain clean separation of concerns.
- **Rich Domain Entities & Actions:** Business logic encapsulated in immutable DTOs and Action classes (`CreateTicketAction`, `UpdateTicketAction`, `ResolveTicketAction`, `CloseTicketAction`, `ReopenTicketAction`, `AssignTicketAction`, `AddTicketMessageAction`).

### 2. Applied Software Design Patterns
- **State Pattern (`App\States\Ticket`):** Ticket state machine with strictly validated lifecycle transitions (`Open`, `InProgress`, `WaitingForCustomer`, `Resolved`, `Closed`).
- **Strategy Pattern (`App\Strategies\Assignment`):** Plug-and-play agent assignment strategies (`RoundRobinAssignment`, `LeastBusyAgentAssignment`, `SkillBasedAssignment`) managed via `TicketAssignmentService`.
- **Chain of Responsibility Pattern (`App\Routing`):** Configurable automated ticket routing pipeline (`VipRoutingRule` → `UrgentPriorityRoutingRule` → `CategoryRoutingRule` → `DefaultRoutingRule`).
- **Command / Action Pattern (`App\Actions`):** Cohesive, reusable business use cases.
- **Decorator Pattern (`App\Services\Notifications`):** Transparently layered notification sender pipeline (`LoggingNotificationSenderDecorator` → `MetricsNotificationSenderDecorator` → `RetryNotificationSenderDecorator` → `EmailNotificationSender`).
- **Specification Pattern (`App\Specifications\Ticket`):** Composable in-memory and database query specifications (`OpenTicketSpecification`, `UrgentTicketSpecification`, `OverdueTicketSpecification`, `AssignedToAgentSpecification`, etc.) supporting `and`, `or`, and `not` boolean operators.
- **Events / Observer Pattern (`App\Events\Tickets`, `App\Observers`):** Decoupled domain lifecycle events (`TicketCreated`, `TicketStatusChanged`, `TicketAssigned`, `TicketMessageAdded`) and `TicketObserver` for automated audit logging.

### 3. Production-Grade Transactional Idempotency
- **Database-Authoritative Integrity:** MySQL composite unique constraints (`scope_type`, `scope_id`, `operation`, `key_hash`) guarantee single-execution semantics.
- **Deterministic Payload Hashing:** SHA-256 canonical request hashing prevents key reuse with differing payloads (`IdempotencyConflictException` → HTTP 409).
- **Concurrency & In-Flight Collision Guard:** Redis distributed locks optimize throughput; concurrent duplicate submissions waiting on processing gracefully return HTTP 409 with `Retry-After: 2` (`IdempotencyInFlightException`).
- **Resource Metadata Persistence:** Captures `resource_type` (e.g. `'ticket'`) and `resource_id` alongside replayed headers and response envelopes.
- **Automatic Background Maintenance:** Scheduled chunked pruning via `IdempotencyKey` and `php artisan model:prune` / `php artisan idempotency:prune`.

### 4. Background Queues & Asynchronous Jobs
- **Queued Notification Dispatching:** `SendTicketNotificationListener` implements `ShouldQueue` on the `notifications` queue with backoff retries, rate limiting (`RateLimited('notifications')`), and exception throttling (`ThrottlesExceptions`).
- **Domain Background Maintenance:**
  - `EscalateOverdueTicketsJob`: Scans and escalates SLA-breached tickets with `ShouldBeUnique` deduplication.
  - `AutoCloseResolvedTicketsJob`: Transitions stale resolved tickets to closed.
  - `ProcessTicketRoutingJob`: Asynchronously routes tickets through the routing rules pipeline.
- **Console Scheduling:** Scheduled execution in `routes/console.php` with `withoutOverlapping()` and `onOneServer()` protection.

### 5. Multi-Stakeholder Transactional Emails
- **Styled Blade Mailables:** `TicketNotificationMail` delivers responsive HTML and plain-text emails.
- **Role-Aware Routing:** Tailored notifications for Customers (ticket receipts, agent assignments, status updates), Support Admins (new ticket triage alerts), and Support Agents (assignment alerts, customer replies, internal notes).
- **Security Boundary:** Internal staff notes are strictly isolated from customer notifications.

### 6. Modern React 19 Single Page Application (SPA)
- **Frontend Stack:** React 19, Vite, Tailwind CSS v4, Lucide React icons, TanStack React Query v5, Zustand state store, and Sonner/Toast notifications.
- **shadcn/ui Architecture:** Accessible UI primitives (`Button`, `Input`, `Select`, `Card`, `Modal`, `Table`, `Badge`, `Tabs`, `Toast`).
- **Live Workflows:** Specification-based quick filtering (Urgent, Overdue SLA, Unassigned), interactive state transition dialogs, strategy-based & manual agent assignment, conversation feed with internal note toggle, status timeline, and JSON audit diff viewer.
- **Automatic Client Resilience:** Axios interceptor with automatic UUIDv4 `Idempotency-Key` headers on mutating requests and automatic backoff retry on HTTP 409 in-flight collisions.

---

## Technology Stack

| Layer | Technologies |
|---|---|
| **Backend Framework** | PHP 8.2+, Laravel 11/12 |
| **Database** | MySQL 8.4 LTS (ACID source of truth) |
| **Cache & Queues** | Redis (Distributed locking, high-speed replay cache, async workers) |
| **Authentication** | JWT Authentication (`php-open-source-saver/jwt-auth`) with RS256 / asymmetric key support |
| **Frontend Framework** | React 19, Vite |
| **Styling & Components**| Tailwind CSS v4, shadcn/ui component architecture, Lucide React |
| **State Management** | TanStack React Query v5 (server state), Zustand (client session state) |
| **Code Quality & Tests**| Pest / PHPUnit (106+ tests, 500+ assertions), Laravel Pint |

---

## Project Structure (Domain-Driven Design)

```text
app/
├── Console/Commands/         # Artisan CLI commands (Custom PruneCommand, etc.)
├── Domain/                   # Bounded Contexts (Core Business Domain)
│   ├── Ticket/               # Ticket Management Subsystem
│   │   ├── Actions/          # Business actions (Create, Update, Resolve, Close, etc.)
│   │   ├── DTOs/             # Immutable data transfer objects
│   │   ├── Enums/            # TicketStatus, TicketPriority
│   │   ├── Events/           # Domain events (TicketCreated, TicketAssigned, etc.)
│   │   ├── Exceptions/       # Domain rule violations
│   │   ├── Jobs/             # Background domain jobs (Escalate, AutoClose, Route)
│   │   ├── Listeners/        # Event listeners (Logging, Notifications)
│   │   ├── Mail/             # Transactional Mailables (TicketNotificationMail)
│   │   ├── Models/           # Ticket, Category, TicketMessage, TicketStatusHistory
│   │   ├── Observers/        # TicketObserver (lifecycle tracking)
│   │   ├── Policies/         # Authorization policies (Ticket, Message, History)
│   │   ├── Providers/        # TicketServiceProvider, TicketEventServiceProvider
│   │   ├── Routing/          # Chain of Responsibility routing rules & pipeline
│   │   ├── Services/         # TicketAssignmentService
│   │   ├── Specifications/   # Composable ticket query specifications
│   │   ├── States/           # State pattern state machines
│   │   └── Strategies/       # Assignment strategies (RoundRobin, LeastBusy, Skill)
│   ├── Identity/             # Identity & Access Subsystem
│   │   ├── Enums/            # Role enum (Admin, Agent, Customer)
│   │   ├── Models/           # User model
│   │   ├── Policies/         # UserPolicy
│   │   └── Providers/        # IdentityServiceProvider
│   └── Audit/                # Audit Logging Subsystem
│       ├── Models/           # AuditLog model
│       ├── Policies/         # AuditLogPolicy
│       ├── Providers/        # AuditServiceProvider
│       └── Services/         # AuditLogger service
├── Infrastructure/           # Technical Mechanisms & Cross-Cutting Capabilities
│   ├── Idempotency/          # IdempotencyManager, Redis store, Key model, Jobs
│   └── Notifications/        # NotificationSenderInterface, Decorators, Providers
└── Http/                     # Transport & Delivery Layer (Web & REST API)
    ├── Controllers/          # API Controllers
    ├── Middleware/           # IdempotentRequest, CheckRole
    ├── Requests/             # Form validation requests
    ├── Resources/            # JSON API resources
    └── Responses/            # Standardized API response envelopes

resources/js/                 # React 19 SPA Frontend
├── components/
│   ├── layout/               # Navbar, Sidebar, Shell
│   └── ui/                   # shadcn-styled primitives (Button, Modal, Table, etc.)
├── features/
│   ├── auth/                 # Sign In & Registration forms
│   ├── tickets/              # Ticket List, Detail, FilterBar, Modals, Messages
│   └── audit/                # Audit Log viewer & JSON diffs
├── lib/                      # Axios client, utils (cn, error parser), query keys
└── stores/                   # Zustand stores (useAuthStore, useUiStore)
```

---

## Installation & Setup

### 1. Prerequisites
- **PHP 8.2+** with extensions (`pdo_mysql`, `redis`, `mbstring`, `bcmath`, `curl`)
- **Composer 2.x**
- **Node.js 20+** & **npm**
- **MySQL 8.4 LTS**
- **Redis 7.x / 8.x**

### 2. Clone & Configure Environment
```bash
git clone https://github.com/your-org/laravel-help-desk.git
cd laravel-help-desk

cp .env.example .env
```

Configure your `.env` database, redis, and mail settings:
```dotenv
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=laravel_help_desk
DB_USERNAME=root
DB_PASSWORD=

REDIS_CLIENT=phpredis
REDIS_HOST=127.0.0.1
REDIS_PORT=6379

QUEUE_CONNECTION=redis
MAIL_MAILER=smtp
MAIL_HOST=127.0.0.1
MAIL_PORT=1025
MAIL_FROM_ADDRESS="support@helpdesk.test"
MAIL_FROM_NAME="HelpDesk Support"
```

### 3. Install Dependencies & Generate Keys
```bash
composer install
npm install

php artisan key:generate
php artisan jwt:secret
```

### 4. Run Migrations & Seed Test Data
```bash
# Runs all database migrations and seeds realistic test data
php artisan migrate:fresh --seed
```

#### Pre-Configured Seed Accounts (Password: `password`)
| Role | Email | Name |
| :--- | :--- | :--- |
| **Admin** | `admin@example.com` | System Administrator |
| **Agent** | `sarah.agent@example.com` | Sarah Connor (Senior Technical) |
| **Agent** | `alex.agent@example.com` | Alex Murphy (Security & Auth) |
| **Agent** | `david.agent@example.com` | David Miller (Billing Specialist) |
| **Agent** | `elena.agent@example.com` | Elena Rostova (General Support) |
| **Customer** | `john.customer@example.com` | John Doe |
| **Customer** | `emily.customer@example.com` | Emily Blunt |
| **Customer** | `bruce.wayne@example.com` | Bruce Wayne (VIP Account) |

### 5. Build Assets & Start Application
```bash
# Build frontend assets
npm run build

# Start local server
php artisan serve
```

For frontend development with live hot-reloading:
```bash
npm run dev
```

### 6. Start Queue Worker
To process background notifications, auto-escalations, and routing jobs:
```bash
php artisan queue:work redis --queue=notifications,default,maintenance
```

---

## REST API Endpoints

All API endpoints are versioned under `/api/v1` and return standardized JSON envelopes. For full endpoint documentation, request/response schemas, validation rules, and query parameters, see **[`docs/API_ENDPOINTS.md`](docs/API_ENDPOINTS.md)**.

### Quick Reference Summary

| Resource | Method & Path | Description | Access |
|---|---|---|---|
| **Auth** | `POST /api/v1/auth/register` | Register customer or agent account | Public |
| **Auth** | `POST /api/v1/auth/login` | Authenticate and obtain JWT Bearer token | Public |
| **Auth** | `GET /api/v1/auth/me` | Fetch authenticated user profile | Authenticated |
| **Users** | `GET /api/v1/agents` | List active support agents | Staff |
| **Tickets** | `GET /api/v1/tickets` | Specification-filtered ticket list | Authenticated |
| **Tickets** | `POST /api/v1/tickets` | Create ticket (`IdempotentRequest` protected) | Authenticated |
| **Tickets** | `GET /api/v1/tickets/{id}` | Retrieve ticket details with relations | Authenticated |
| **Tickets** | `PUT /api/v1/tickets/{id}` | Update ticket metadata | Staff / Owner |
| **Lifecycle** | `POST /api/v1/tickets/{id}/transition` | State pattern status transition | Staff |
| **Lifecycle** | `POST /api/v1/tickets/{id}/resolve` | Resolve ticket with notes | Staff |
| **Lifecycle** | `POST /api/v1/tickets/{id}/close` | Close ticket | Staff / Owner |
| **Lifecycle** | `POST /api/v1/tickets/{id}/reopen` | Reopen resolved/closed ticket | Staff / Owner |
| **Assignment**| `POST /api/v1/tickets/{id}/assign` | Strategy or manual agent assignment | Staff |
| **Routing** | `POST /api/v1/tickets/{id}/route` | Chain of Responsibility routing pipeline | Staff |
| **Messages** | `GET /api/v1/tickets/{id}/messages` | List conversation messages | Authenticated |
| **Messages** | `POST /api/v1/tickets/{id}/messages` | Post reply or internal staff note | Authenticated |
| **Audit** | `GET /api/v1/tickets/{id}/status-history` | Ticket status progression timeline | Authenticated |
| **Audit** | `GET /api/v1/tickets/{id}/audit-logs` | Ticket-level mutation audit diffs | Staff |
| **Audit** | `GET /api/v1/audit-logs` | Browse global system audit trail | Admin |

👉 *Full specifications, query filters, and sample JSON payloads: **[`docs/API_ENDPOINTS.md`](docs/API_ENDPOINTS.md)***

The API collection for this project is committed directly to the repository. You can download or import the file into Postman using the link below:

👉 [Download Postman Collection](storage/postman/api_collection.json)

---

## Testing & Quality Assurance

The application includes a comprehensive automated test suite testing domain logic, pattern invariants, API contracts, authorization policies, concurrency, and queue jobs.

```bash
# Run all automated tests in parallel
php artisan test --parallel

# Verify code style compliance with Laravel Pint
./vendor/bin/pint --test

# Fix code style formatting automatically
./vendor/bin/pint
```

### Test Suite Highlights
- **106+ Tests, 500+ Assertions** across Unit and Feature test suites.
- **Patterns Test Suite (`tests/Feature/Patterns/`):** Full verification of State transitions, Strategy assignments, Chain of Responsibility routing, Command actions, Decorator pipeline, Events/Observers, and Specifications.
- **Idempotency Test Suite (`tests/Feature/Idempotency/`):** Tests replay caching, canonical hashing, distributed lock timeouts, and `IdempotencyInFlightException` handling.
- **Authorization Test Suite (`tests/Feature/Authorization/`):** Tests cross-tenant boundaries, role gates, and policy permissions.
- **Queue & Mail Test Suite (`tests/Feature/Jobs/`, `tests/Feature/Notifications/`):** Tests background jobs, unique job locks, mailables, and multi-stakeholder notification delivery.

---

## Documentation Index

Detailed architectural deep-dives and design specifications are maintained in the [`docs/`](docs/) directory:

- [`docs/API_ENDPOINTS.md`](docs/API_ENDPOINTS.md) — Comprehensive REST API endpoint reference, request/response JSON schemas, and query filters.
- [`docs/PROJECT.md`](docs/PROJECT.md) — Product requirements, user personas, and scope.
- [`docs/ARCHITECTURE.md`](docs/ARCHITECTURE.md) — System architecture, delivery layers, and multi-tenancy roadmap.
- [`docs/DDD_ARCHITECTURE_GUIDE.md`](docs/DDD_ARCHITECTURE_GUIDE.md) — Comprehensive guide on DDD Bounded Contexts, domain service providers, and directory structures.
- [`docs/DATABASE.md`](docs/DATABASE.md) — Relational database schema, table definitions, and indexing strategy.
- [`docs/PATTERNS.md`](docs/PATTERNS.md) — Architectural pattern catalog with code examples and implementations.
- [`docs/DECISIONS.md`](docs/DECISIONS.md) — Architecture Decision Records (ADRs).
- [`docs/IDEMPOTENCY_IMPLEMENTATION_NOTES.md`](docs/IDEMPOTENCY_IMPLEMENTATION_NOTES.md) — Idempotency subsystem architecture and concurrency model.
- [`docs/ROADMAP.md`](docs/ROADMAP.md) — Feature progress, completed milestones, and future phases.

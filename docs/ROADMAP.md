# Project Roadmap

## Phase 0 — Foundation & Architecture
- [x] Create Laravel 11/12 application with PHP 8.2+
- [x] Configure MySQL 8.4 LTS as ACID transactional database
- [x] Configure Redis for locks, cache, and async queues
- [x] Set up Pest / PHPUnit test infrastructure
- [x] Domain-Driven Design (DDD) layout (`Domain/Ticket`, `Domain/Identity`, `Domain/Audit`, `Infrastructure/`)
- [x] Modular Domain Service Providers (`TicketServiceProvider`, `TicketEventServiceProvider`, etc.)

## Phase 1 — Authentication & Authorization
- [x] JWT asymmetric authentication (`php-open-source-saver/jwt-auth`)
- [x] User models and roles (`ADMIN`, `AGENT`, `CUSTOMER`)
- [x] Standardized API authentication endpoints (`/login`, `/register`, `/me`, `/refresh`, `/logout`)
- [x] Granular Laravel Policies (`TicketPolicy`, `TicketMessagePolicy`, `TicketStatusHistoryPolicy`, `AuditLogPolicy`, `UserPolicy`)
- [x] HTTP 403 Forbidden structured error responses

## Phase 2 — Core Ticketing & Composable Queries
- [x] Category and Ticket entities with rich database migrations
- [x] Form validation requests with custom rules and multibyte safety
- [x] Composable Specification Pattern (`TicketSpecification`, `OpenTicketSpecification`, `UrgentTicketSpecification`, `OverdueTicketSpecification`, etc.)
- [x] API endpoints for ticket creation, updating, retrieval, and deletion
- [x] Standardized API responses (`ApiResponse`) and API Resources (`TicketResource`)

## Phase 3 — Ticket Conversations & Messages
- [x] `TicketMessage` entity and migration
- [x] Public conversation replies between customer and agent
- [x] Private internal staff notes with strict customer authorization boundaries
- [x] `AddTicketMessageAction` with event dispatching

## Phase 4 — Lifecycle & State Machine
- [x] State Pattern implementation (`TicketState`, `OpenTicketState`, `InProgressTicketState`, `WaitingForCustomerTicketState`, `ResolvedTicketState`, `ClosedTicketState`)
- [x] Validated transition rules and `InvalidTicketStateTransitionException`
- [x] Dedicated `TicketStatusHistory` timeline tracking (from, to, reason, agent)
- [x] REST endpoints (`/transition`, `/resolve`, `/close`, `/reopen`)

## Phase 5 — Assignment & Routing
- [x] Strategy Pattern for ticket assignments:
  - [x] `RoundRobinAssignment`
  - [x] `LeastBusyAgentAssignment`
  - [x] `SkillBasedAssignment`
- [x] Chain of Responsibility Pattern for automated ticket routing (`VipRoutingRule` → `UrgentPriorityRoutingRule` → `CategoryRoutingRule` → `DefaultRoutingRule`)
- [x] Ticket assignment and routing REST endpoints (`/assign`, `/route`)

## Phase 6 — Audit Logging & Transactional Notifications
- [x] Polymorphic `AuditLog` model and `AuditLogger` service with sensitive field masking
- [x] Observers for automatic model change tracking (`TicketObserver`)
- [x] Decorator Pattern for notifications (`LoggingNotificationSenderDecorator` → `MetricsNotificationSenderDecorator` → `RetryNotificationSenderDecorator` → `EmailNotificationSender`)
- [x] Transactional Blade mailables (`TicketNotificationMail`) with responsive HTML & plain-text templates
- [x] Stakeholder-specific email routing (Customer receipts/updates, Agent assignment/replies, Admin triage)

## Phase 7 — Background Queues & Asynchronous Jobs
- [x] Redis queue worker integration
- [x] Queued listeners (`SendTicketNotificationListener` implementing `ShouldQueue` with backoff and rate limiting)
- [x] Transaction-safe event dispatching (`ShouldDispatchAfterCommit`)
- [x] Domain background jobs:
  - [x] `EscalateOverdueTicketsJob` (with `ShouldBeUnique`)
  - [x] `AutoCloseResolvedTicketsJob` (with `ShouldBeUnique`)
  - [x] `ProcessTicketRoutingJob` (with `ShouldBeUnique`)
  - [x] `PruneExpiredIdempotencyKeysJob` (with `ShouldBeUnique`)
- [x] Scheduled cron jobs and custom Artisan commands in `routes/console.php`

## Phase 8 — Transactional Idempotency & Concurrency
- [x] Database-authoritative `idempotency_keys` table with composite unique index
- [x] Canonical SHA-256 request payload hashing
- [x] `RedisIdempotencyStore` for high-speed replay cache and distributed locks
- [x] In-flight concurrency handling with `IdempotencyInFlightException` (`HTTP 409 Retry-After: 2`)
- [x] `IdempotentRequest` route middleware capturing `resource_type` and `resource_id`
- [x] Pruning integration with `MassPrunable` and custom `php artisan model:prune`

## Phase 9 — React 19 Frontend SPA
- [x] Vite + React 19 Single Page Application setup
- [x] Tailwind CSS v4 styling & shadcn/ui accessible component primitives
- [x] TanStack React Query v5 caching & optimistic updates
- [x] Zustand state store (`useAuthStore`, `useUiStore`)
- [x] Full UI workflows: ticket list with quick filters, ticket creation modal, state machine dialogs, assignment selectors, conversation timeline with internal note toggle, status progression visualizer, and JSON audit diff explorer
- [x] Axios client with automated UUIDv4 `Idempotency-Key` headers and backoff retry interceptor

## Phase 10 — Seeders & Comprehensive Test Suite
- [x] Deterministic seed accounts for Admins, Agents, and Customers (`UserSeeder`, `CategorySeeder`, `TicketSeeder`, `DatabaseSeeder`)
- [x] 106+ automated tests and 500+ assertions with Pest / PHPUnit
- [x] Laravel Pint code style compliance

---

## Future Milestones

### Phase 11 — AI Integration (Laravel AI SDK)
- [ ] Automated ticket categorization and priority suggestion
- [ ] Ticket thread summarization for agents
- [ ] Smart reply suggestions based on knowledge base articles
- [ ] RAG (Retrieval-Augmented Generation) search on support documentation

### Phase 12 — Multi-Tenant SaaS
- [ ] Tenant model and tenant database scoping
- [ ] Custom domain mapping and white-labeling
- [ ] Subscription tiers, quotas, and billing integration (Stripe / LemonSqueezy)
- [ ] Multi-tenant queue and cache partitioning

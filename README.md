# HelpDesk

A backend-first HelpDesk REST API built with **PHP 8.5**, **Laravel 13**, **MySQL 8.4 LTS**, and **Redis**.

The project is primarily a practical learning project for revisiting modern PHP, Laravel, REST API design, database design, Redis, testing, SOLID principles, and design patterns.

## Project Vision

The initial application is a **single-tenant HelpDesk**.

The longer-term goal is to evolve it into a **multi-tenant SaaS HelpDesk** without prematurely introducing SaaS complexity into the first implementation.

```text
                    HelpDesk
                       |
              ┌────────┴────────┐
              |                 |
       REST API Backend    Future Frontends
              |             /    |    \
              |          Blade React Vue
              |
       ┌──────┼──────┐
       |      |      |
     MySQL  Redis  Laravel
```

## Technology Stack

| Technology | Purpose |
|---|---|
| PHP 8.5 | Application language |
| Laravel 13 | Backend/API framework |
| MySQL 8.4 LTS | Primary relational database |
| Redis | Queues, caching, and appropriate distributed coordination |
| Laravel Boost | Development/AI coding assistance |
| Laravel AI SDK | Planned future AI features |

## Current Focus

The current focus is **backend implementation only**.

### In scope

- REST API
- Authentication and authorization
- Users and roles
- Tickets
- Ticket messages
- Categories
- Ticket assignment
- Ticket status lifecycle
- Ticket status history
- Audit logging
- Idempotent API operations
- Events and listeners
- Jobs and queues
- Redis
- Notifications
- Automated tests
- API documentation
- Production-oriented backend practices

### Deferred

- Blade frontend
- React frontend
- Vue frontend
- Multi-tenancy implementation
- SaaS billing/subscriptions
- Advanced AI functionality

## Planned Design Patterns

Patterns will be introduced only when they solve a real problem.

- Strategy
- Factory
- State
- Chain of Responsibility
- Command / Action
- Adapter
- Decorator
- Events / Observer
- Specification
- Builder
- Repository where genuinely justified

Example:

```text
TicketAssignmentStrategy
├── RoundRobinAssignment
├── LeastBusyAgentAssignment
└── SkillBasedAssignment
```

## Core Domain

The initial domain is expected to include:

```text
User
Category
Ticket
TicketMessage
TicketStatusHistory
AuditLog
Skill
UserSkill
TicketSkill
AssignmentCursor
```

Not every table will be introduced immediately. The schema will evolve with the features.

## Ticket Lifecycle

The initial lifecycle is expected to be:

```text
OPEN
  |
  v
IN_PROGRESS
  |
  v
WAITING_FOR_CUSTOMER
  |
  v
IN_PROGRESS
  |
  v
RESOLVED
  |
  v
CLOSED
```

Valid transitions will eventually be enforced as domain rules rather than relying only on controller conditionals.

## Idempotency

Ticket creation will support an idempotency key.

For the current single-tenant implementation:

```text
UNIQUE(idempotency_key)
```

When the application becomes multi-tenant, this will likely become:

```text
UNIQUE(tenant_id, idempotency_key)
```

A dedicated idempotency table may be introduced later if multiple API operations need idempotency.

## Redis

Redis is intentionally used for practical backend scenarios:

```text
Redis
├── Queues
│   ├── notification jobs
│   └── background processing
│
├── Cache
│   └── appropriate read-heavy data
│
└── Locks
    └── concurrency-sensitive operations
```

Redis will not replace MySQL as the source of truth for transactional business data.

## AI Roadmap

Laravel AI SDK will be introduced after the core HelpDesk is understood and working.

Potential features:

- Ticket classification
- Ticket summarization
- Suggested replies
- Knowledge-base search
- Embeddings/RAG
- HelpDesk AI agent and tools

AI should assist the application and its users rather than replace core deterministic business rules.

## Future Multi-Tenant SaaS

The future architecture may look like:

```text
SaaS Platform
│
├── Tenant A
│   ├── Users
│   ├── Agents
│   └── Tickets
│
├── Tenant B
│   ├── Users
│   ├── Agents
│   └── Tickets
│
└── Tenant C
    ├── Users
    ├── Agents
    └── Tickets
```

The current application remains single-tenant so that we can focus on learning the HelpDesk domain and Laravel fundamentals first.

Future concerns include:

- Tenant isolation
- Tenant-aware authorization
- Tenant-aware cache keys
- Tenant-aware queues
- Tenant-aware files
- Tenant-specific limits
- Plans/subscriptions
- Billing
- AI usage tracking

## Documentation

Detailed project context is maintained in:

```text
.ai/
└── SKILL.md

docs/
├── PROJECT.md
├── ARCHITECTURE.md
├── DATABASE.md
├── PATTERNS.md
├── DECISIONS.md
└── ROADMAP.md
```

### Documentation purpose

- `SKILL.md` — instructions for AI/development assistance
- `PROJECT.md` — project purpose and scope
- `ARCHITECTURE.md` — architectural direction
- `DATABASE.md` — database design
- `PATTERNS.md` — design-pattern learning plan
- `DECISIONS.md` — important architectural decisions
- `ROADMAP.md` — current progress and next steps

## Development Philosophy

This project is intentionally not a "CRUD tutorial".

For significant features, we want to understand:

```text
Requirement
    ↓
Domain model
    ↓
Database design
    ↓
API contract
    ↓
Business rules
    ↓
Authorization
    ↓
Implementation
    ↓
Tests
    ↓
Performance / concurrency
    ↓
Documentation
```

Patterns and abstractions should be introduced because they solve a problem—not simply because they exist.

## Roadmap

1. Project foundation
2. Authentication and users
3. Core ticketing
4. Ticket conversations
5. Ticket lifecycle
6. Assignment strategies
7. Audit and notifications
8. Redis and queues
9. API robustness and idempotency
10. AI capabilities
11. Multi-tenant SaaS

See [`docs/ROADMAP.md`](docs/ROADMAP.md) for the detailed roadmap.

## Current Milestone

**Phase 0 — Project foundation**

The Laravel 13 project has been created locally.

Next:

1. Configure MySQL
2. Configure Redis
3. Establish API conventions
4. Design initial migrations
5. Implement the first core domain models

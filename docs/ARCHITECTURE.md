# Architecture

## Current architecture

The application is a Laravel 13 REST API backed by MySQL and Redis.

```text
API Client
   |
   v
Routes
   |
   v
Controllers
   |
   +--> Form Requests / Validation
   |
   +--> Policies / Authorization
   |
   v
Application / Domain Logic
   |
   +--> Models / Eloquent
   +--> Events / Listeners
   +--> Jobs / Queues
   +--> Notifications
   |
   +--> Redis
   |
   v
MySQL
```

## Core domain

Initial entities:

- User
- Ticket
- TicketMessage
- Category
- TicketStatusHistory
- AuditLog
- Skill
- UserSkill
- TicketSkill
- AssignmentCursor

Some entities will only be introduced when the corresponding feature is implemented.

## Architectural principles

### Thin controllers

Controllers should primarily coordinate HTTP concerns:

- receive request
- authorize
- call application/domain logic
- return API response

### Business logic

Business rules should not be buried inside controllers.

Use services/actions/domain objects when they provide a clear responsibility.

### Eloquent

Use Eloquent naturally. Do not introduce repositories solely to hide Eloquent.

### Events

Use events for meaningful domain occurrences such as:

- TicketCreated
- TicketAssigned
- TicketStatusChanged
- TicketMessageAdded
- TicketResolved

Listeners can handle secondary concerns such as notifications and audit logging.

### Queues

Use Redis-backed queues for work that should happen asynchronously, such as notifications and other suitable background processing.

### Redis

Redis is intended for:

- queues
- cache
- distributed/concurrency coordination where justified

Do not use Redis as a replacement for MySQL transactional data.

## Future multi-tenancy

The eventual SaaS architecture is expected to use shared database/shared tables initially, with tenant_id on tenant-owned records.

Tenant isolation must eventually be enforced at multiple layers:

- authorization
- query scoping
- unique constraints
- cache keys
- queue/job context
- file paths
- API behavior

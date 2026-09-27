# Roadmap

## Phase 0 — Project foundation

- [x] Create Laravel 13 project
- [ ] Configure MySQL
- [ ] Configure Redis
- [ ] Establish local development workflow
- [ ] Add project documentation

## Phase 1 — Authentication and users

- [ ] Authentication
- [ ] User roles
- [ ] User profile
- [ ] Authorization foundations
- [ ] API authentication

## Phase 2 — Core ticketing

- [ ] Categories
- [ ] Ticket creation
- [ ] Ticket listing
- [ ] Ticket details
- [ ] Ticket updates
- [ ] Ticket deletion/archival rules
- [ ] Pagination
- [ ] Filtering/search

## Phase 3 — Conversations

- [ ] Ticket messages
- [ ] Message authorization
- [ ] Message pagination
- [ ] Internal notes if required

## Phase 4 — Ticket lifecycle

- [ ] TicketStatus enum
- [ ] Valid state transitions
- [ ] Ticket status history
- [ ] Events for lifecycle changes
- [ ] State pattern evaluation

## Phase 5 — Assignment

- [ ] Assignment interface
- [ ] RoundRobinAssignment
- [ ] LeastBusyAgentAssignment
- [ ] SkillBasedAssignment
- [ ] Assignment factory
- [ ] Concurrency handling
- [ ] Assignment tests

## Phase 6 — Audit and notifications

- [ ] Audit log
- [ ] Notification abstraction
- [ ] Adapter pattern
- [ ] Decorator pattern
- [ ] Email notification
- [ ] Database notifications

## Phase 7 — Redis and queues

- [ ] Queue configuration
- [ ] Background jobs
- [ ] Job retries
- [ ] Failed jobs
- [ ] Cache
- [ ] Appropriate distributed locks

## Phase 8 — API robustness

- [ ] Idempotent ticket creation
- [ ] Rate limiting
- [ ] Consistent error responses
- [ ] API Resources
- [ ] API documentation
- [ ] Integration tests

## Phase 9 — AI

- [ ] Laravel AI SDK
- [ ] Ticket classification
- [ ] Ticket summarization
- [ ] Suggested replies
- [ ] Knowledge base
- [ ] Embeddings/RAG
- [ ] HelpDesk AI agent/tools

## Phase 10 — Multi-tenant SaaS

- [ ] Tenant model
- [ ] Tenant isolation
- [ ] Tenant-aware authorization
- [ ] Tenant-aware cache
- [ ] Tenant-aware queues
- [ ] Tenant-aware files
- [ ] Plans
- [ ] Subscriptions
- [ ] Usage limits
- [ ] Billing
- [ ] AI usage tracking

## Current milestone

Phase 0 — Project foundation.

## Next milestone

Configure the Laravel 13 project for MySQL and Redis, establish the initial API conventions, and then design/implement the first database migrations.

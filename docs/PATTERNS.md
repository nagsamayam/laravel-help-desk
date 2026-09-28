# Design Patterns

The project intentionally uses design patterns as learning exercises when they solve real problems.

## Strategy

Planned use:

Ticket assignment.

Implementations:

- RoundRobinAssignment
- LeastBusyAgentAssignment
- SkillBasedAssignment

Potential future:

- AIAssignmentStrategy

## Factory

Potential use:

Creating/selecting assignment strategies or notification implementations.

Do not create factories where Laravel dependency injection/container configuration already provides a simpler solution.

## State

Potential use:

Ticket lifecycle and valid status transitions.

Example states:

- Open
- In Progress
- Waiting for Customer
- Resolved
- Closed

## Chain of Responsibility

Potential use:

Automatic ticket routing rules.

Example:

VIP rule → urgent rule → category rule → default rule.

## Command / Action

Potential use:

Explicit application actions:

- CreateTicket
- ResolveTicket
- CloseTicket
- ReopenTicket
- AssignTicket
- AddTicketMessage

`CreateTicket` is an application command/action. When it is idempotent, it executes its database mutation inside the idempotency transaction boundary.

## Idempotency Manager

Purpose:

Provide one reusable boundary for transactional API idempotency without coupling individual domain models to idempotency columns.

Responsibilities:

- key validation
- scoped uniqueness
- request fingerprinting
- concurrency handling through the database unique constraint
- response persistence and replay
- retention metadata

This is treated as a cross-cutting application/infrastructure service rather than a domain pattern forced onto tickets.

## Adapter

Potential use:

External providers whose API differs from the application's internal interface.

Examples:

- email provider
- Slack
- SMS provider

## Decorator

Potential use:

Adding cross-cutting behavior around an existing interface without modifying the underlying implementation.

Example learning chain:

LoggingDecorator → MetricsDecorator → NotificationSender

RetryNotificationSender may be used as a learning example, but retry behavior does not have to be implemented as the final notification architecture.

## Events / Observer

Potential use:

Domain events such as TicketCreated and TicketStatusChanged.

Secondary concerns can subscribe without coupling the core ticket operation to every side effect.

## Specification

Potential use:

Reusable, composable ticket filtering/business rules when filtering becomes sufficiently complex.

## Builder

Potential use:

Complex query/report construction where Eloquent scopes or the existing query builder are no longer sufficient.

## Repository

Not a default requirement.

Use only when there is a genuine abstraction need, such as multiple data sources or a complex persistence boundary.

## Pattern rule

Never introduce a pattern only because the pattern exists.

First identify the problem, then choose the pattern that makes the solution clearer.

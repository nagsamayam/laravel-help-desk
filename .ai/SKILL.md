# HelpDesk Project — AI Working Guidelines

## Project purpose

Help build a backend-first HelpDesk REST API as a learning project for modern PHP 8.5 and Laravel 13.

The application will initially be single-tenant, while its architecture should keep future multi-tenant SaaS conversion in mind.

## Technology stack

- PHP 8.5
- Laravel 13
- MySQL 8.4 LTS
- Redis
- REST API

Frontend technologies such as Blade, React, or Vue are intentionally deferred.

## Learning principles

1. Prefer standard Laravel solutions before introducing abstractions.
2. Introduce design patterns only when there is a real domain or architectural reason.
3. Explain the PHP/Laravel concept being exercised when introducing significant code.
4. Prefer modern PHP features where they improve clarity:
   - enums
   - readonly properties/classes
   - constructor property promotion
   - typed properties and parameters
   - match expressions
   - attributes where appropriate
5. Keep controllers thin and business rules testable.
6. Use dependency injection rather than unnecessary static/global coupling.
7. Do not create repositories, factories, DTOs, managers, or interfaces merely for ceremony.
8. Security, authorization, tenant isolation, idempotency, concurrency, and data integrity should be treated as first-class concerns.
9. Prefer explicit, readable code over clever abstractions.
10. Every important architectural decision should be recorded in docs/DECISIONS.md.

## Design patterns to practice deliberately

Potential patterns include:

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

Patterns are learning goals, not mandatory architectural requirements.

## Future SaaS direction

The future system may become multi-tenant SaaS.

For now:
- implement a normal single-tenant HelpDesk;
- avoid premature multi-tenancy;
- when designing persistent data, consider where tenant_id, tenant-aware cache keys, queues, files, authorization, and unique constraints would eventually fit.

## AI development tools

Laravel Boost may be used from day one as a development aid.

Laravel AI SDK is planned for a later phase and should not replace understanding or implementing the core application.

## Development style

For every significant feature, aim for:

1. Domain requirement
2. Data model
3. API contract
4. Business logic
5. Authorization/security
6. Tests
7. Performance/concurrency considerations
8. Documentation/decision update

## Current scope

Backend / REST API only.

Frontend is postponed.

# Design Patterns Catalog

The HelpDesk project implements enterprise design patterns to solve concrete architectural and business requirements, maintaining strict typing, boundary isolation, and high testability.

---

## 1. State Pattern (`App\Domain\Ticket\States`)

### Problem
Ticket lifecycles have complex transition rules (e.g., a ticket cannot transition directly from `Open` to `Closed` without resolution, or from `Closed` to `InProgress` without reopening). Hardcoding conditionals in controllers or models produces brittle, bug-prone code.

### Implementation
- **Base State:** `TicketState` defines abstract capabilities (`canTransitionTo`, `transitionTo`, `resolve`, `close`, `reopen`).
- **Concrete States:** `OpenTicketState`, `InProgressTicketState`, `WaitingForCustomerTicketState`, `ResolvedTicketState`, `ClosedTicketState`.
- **Domain Exception:** `InvalidTicketStateTransitionException` is raised when an invalid transition is attempted.
- **Model Integration:** `Ticket::state()` returns the current state object; `$ticket->transitionTo(TicketStatus::Resolved)` delegates to the state object.

```php
// app/Domain/Ticket/States/OpenTicketState.php
final class OpenTicketState extends TicketState
{
    public function canTransitionTo(TicketStatus $targetStatus): bool
    {
        return in_array($targetStatus, [
            TicketStatus::InProgess,
            TicketStatus::WaitingForCustomer,
            TicketStatus::Resolved,
            TicketStatus::Closed,
        ], true);
    }
}
```

---

## 2. Strategy Pattern (`App\Domain\Ticket\Strategies`)

### Problem
Support tickets need to be assigned to agents based on different algorithmic criteria (Round Robin, Agent Workload, or Agent Skill Match) that can be selected dynamically at runtime.

### Implementation
- **Strategy Interface:** `AssignmentStrategy` declares `assign(Ticket $ticket, Collection $availableAgents): ?User`.
- **Concrete Strategies:**
  - `RoundRobinAssignment`: Rotates assignments across active agents using database locks/cursors.
  - `LeastBusyAgentAssignment`: Assigns tickets to the agent with the lowest count of active (`OPEN`, `IN_PROGRESS`) tickets.
  - `SkillBasedAssignment`: Matches ticket category/keywords with agent domain proficiencies.
- **Service Orchestrator:** `TicketAssignmentService` manages strategy resolution and execution.

```php
// app/Domain/Ticket/Strategies/Assignment/LeastBusyAgentAssignment.php
final class LeastBusyAgentAssignment implements AssignmentStrategy
{
    public function assign(Ticket $ticket, Collection $availableAgents): ?User
    {
        return $availableAgents->sortBy(fn (User $agent) => $agent->assignedTickets()
            ->whereIn('status', [TicketStatus::Open, TicketStatus::InProgess])
            ->count()
        )->first();
    }
}
```

---

## 3. Chain of Responsibility Pattern (`App\Domain\Ticket\Routing`)

### Problem
Incoming tickets must be evaluated against a series of business routing rules (VIP customers, urgent outages, category-specific teams, fallback defaults) without coupling rules together or nesting massive `if/else` ladders.

### Implementation
- **Handler Interface:** `TicketRoutingRule` with `setNext(TicketRoutingRule $next)` and `handle(Ticket $ticket): ?TicketRoutingDecision`.
- **Concrete Rules:**
  - `VipRoutingRule`: Checks if customer belongs to VIP organization tier.
  - `UrgentPriorityRoutingRule`: Routes critical severity tickets to senior on-call agents.
  - `CategoryRoutingRule`: Matches department categories (e.g. Billing vs Technical).
  - `DefaultRoutingRule`: Fallback rule ensuring every ticket receives a routing decision.
- **Pipeline Builder:** `TicketRouter::buildDefault()` chains handlers in order.

```php
// app/Domain/Ticket/Routing/TicketRouter.php
$router = (new VipRoutingRule)
    ->setNext(new UrgentPriorityRoutingRule)
    ->setNext(new CategoryRoutingRule)
    ->setNext(new DefaultRoutingRule);

$decision = $router->handle($ticket);
```

---

## 4. Command / Action Pattern (`App\Domain\Ticket\Actions`)

### Problem
Complex business operations (creating a ticket, resolving with audit logs and events, adding messages) should not be bound to HTTP controller lifecycles so they can be reused across API controllers, Artisan CLI commands, background queue jobs, and seeders.

### Implementation
- **Action Classes:** Single-responsibility, immutable use-case actions:
  - `CreateTicketAction` (accepts `CreateTicketData` DTO)
  - `UpdateTicketAction` (accepts `UpdateTicketData` DTO)
  - `ResolveTicketAction`
  - `CloseTicketAction`
  - `ReopenTicketAction`
  - `AssignTicketAction`
  - `AddTicketMessageAction`

```php
// app/Domain/Ticket/Actions/ResolveTicketAction.php
final readonly class ResolveTicketAction
{
    public function execute(Ticket $ticket, string $resolutionNotes): Ticket
    {
        $ticket->transitionTo(TicketStatus::Resolved, $resolutionNotes);
        return $ticket->fresh(['category', 'customer', 'assignee']);
    }
}
```

---

## 5. Decorator Pattern (`App\Infrastructure\Notifications`)

### Problem
Outbound notification sending requires cross-cutting concerns (structured logging, dispatch metrics, exponential retry on mail transport errors) without polluting the core `EmailNotificationSender` class.

### Implementation
- **Contract:** `NotificationSenderInterface` (`send(...)`).
- **Core Component:** `EmailNotificationSender` (sends real Laravel Blade `TicketNotificationMail` mailables).
- **Decorators:**
  - `LoggingNotificationSenderDecorator`: Logs payload context and result.
  - `MetricsNotificationSenderDecorator`: Captures execution duration and success rates.
  - `RetryNotificationSenderDecorator`: Retries transient mail transport exceptions with backoff.
- **Container Wiring:** Registered in `NotificationServiceProvider` to compose the decorator stack automatically.

```text
NotificationSenderInterface
       │
       ▼
[Logging Decorator] ──> [Metrics Decorator] ──> [Retry Decorator] ──> [EmailNotificationSender]
```

---

## 6. Specification Pattern (`App\Domain\Ticket\Specifications`)

### Problem
Complex ticket queries (e.g., finding tickets that are urgent OR overdue, assigned to an agent, and not resolved) need to be reusable both in database queries (`Eloquent Builder`) and in-memory evaluation.

### Implementation
- **Base Specification:** `TicketSpecification` defining `isSatisfiedBy(Ticket $ticket): bool`, `apply(Builder $query): Builder`, `and()`, `or()`, and `not()`.
- **Concrete Specifications:**
  - `OpenTicketSpecification`
  - `StatusTicketSpecification`
  - `PriorityTicketSpecification`
  - `UrgentTicketSpecification`
  - `AssignedToAgentSpecification`
  - `UnassignedTicketSpecification`
  - `CustomerTicketsSpecification`
  - `OverdueTicketSpecification`
- **Model Macro:** `Ticket::matching(TicketSpecification $spec)` builds the query automatically.

```php
$spec = (new UrgentTicketSpecification())
    ->or(new OverdueTicketSpecification())
    ->and(new UnassignedTicketSpecification());

$tickets = Ticket::matching($spec)->get();
```

---

## 7. Events / Observer Pattern (`App\Domain\Ticket\Events`, `App\Domain\Ticket\Observers`)

### Problem
Ticket operations trigger secondary side-effects (audit logs, stakeholder emails, webhook dispatches) that should be decoupled from the primary database transaction.

### Implementation
- **Domain Events:** `TicketCreated`, `TicketStatusChanged`, `TicketAssigned`, `TicketMessageAdded` (implementing `ShouldDispatchAfterCommit`).
- **Model Observer:** `TicketObserver` listens to Eloquent lifecycle hooks (`created`, `updated`, `deleted`) to generate `AuditLog` and `TicketStatusHistory` records.
- **Queued Listeners:** `SendTicketNotificationListener` and `LogTicketActivityListener` process asynchronous notifications and system logging on Redis queues.

---

## 8. Idempotency Manager (`App\Infrastructure\Idempotency`)

### Problem
Clients submitting mutating API requests over unstable networks may retry requests, potentially causing duplicate tickets or double billing.

### Implementation
- **Core Coordinator:** `IdempotencyManager` coordinates canonical SHA-256 request hashing, composite MySQL unique constraint locking, and deterministic JSON response caching/replay.
- **Redis Optimization:** `RedisIdempotencyStore` manages high-speed replay cache and concurrency locks, gracefully falling back to database ACID guarantees.
- **HTTP Middleware:** `IdempotentRequest` seamlessly wraps routes, handles UUIDv4 `Idempotency-Key` headers, and automatically returns `Idempotency-Replayed: true` on duplicate requests.

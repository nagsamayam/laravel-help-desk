# HelpDesk

A modern HelpDesk application built with **PHP 8.2+ / Laravel 13**, **MySQL 8.4 LTS**, **Redis**, and a **React 19 Single Page Application (SPA)** styled with **Tailwind CSS v4** and **shadcn/ui** components.

Developed exclusively for learning purposes and technical skill refreshment, The project demonstrates Domain-Driven Design (DDD), software design patterns, resilient transactional idempotency, background job queues, multi-stakeholder email notifications, and comprehensive test coverage.

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
| **Queue Supervision** | Laravel Horizon (Real-time queue monitoring, auto-scaling, metrics dashboard) |
| **Authentication** | JWT Authentication (`php-open-source-saver/jwt-auth`) with RS256 / asymmetric key support |
| **Frontend Framework** | React 19, Vite |
| **Styling & Components**| Tailwind CSS v4, shadcn/ui component architecture, Lucide React |
| **State Management** | TanStack React Query v5 (server state), Zustand (client session state) |
| **Real-time Engine** | Laravel Reverb (WebSockets), Laravel Echo, Pusher-JS |
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
- **Docker & Docker Compose** (for containerized MySQL 8.4 & Redis services)

---

### 2. Start MySQL & Redis via Docker Compose

The project includes a ready-to-run `compose.yml` defining **MySQL 8.4 LTS** and **Redis (Alpine)** with health checks and persistent volume storage.

1. **Start the containers in detached mode:**
   ```bash
   docker compose up -d
   ```

2. **Verify container health and port bindings:**
   ```bash
   docker compose ps
   ```
   *Expected output:*
   - `mysql` listening on `0.0.0.0:3306->3306` (Status: `healthy`)
   - `redis` listening on `0.0.0.0:6379->6379` (Status: `healthy`)

3. **Useful Docker management commands:**
   ```bash
   # View container logs
   docker compose logs -f

   # Test Redis connectivity
   docker compose exec redis redis-cli ping
   # Output: PONG

   # Connect to MySQL CLI
   docker compose exec mysql mysql -u root -p
   
   # Stop containers (preserves volume data)
   docker compose down
   ```


---

### 3. Percona Monitoring and Management (PMM)

The project includes **Percona Monitoring and Management (PMM)** for local MySQL observability. PMM provides MySQL performance dashboards and Query Analytics (QAN) for inspecting database activity, query performance, connections, and other production-style database metrics.

The Docker Compose setup includes:

- **`pmm-server`** — PMM Server and dashboard UI, available at `https://localhost:8443`.
- **`pmm-client`** — PMM Agent/client used to register the local MySQL service with PMM.
- **`mysql`** — MySQL 8.4 LTS database being monitored.

#### 3.1 Create the PMM MySQL Monitoring User

If the PMM client is configured to use the dedicated `pmm_monitor` account, create the account inside MySQL before registering the database with PMM:

```sql
CREATE USER 'pmm_monitor'@'%' IDENTIFIED BY 'pmm_password';

GRANT SELECT, PROCESS, REPLICATION CLIENT, RELOAD
ON *.* TO 'pmm_monitor'@'%';
```

> **Note:** These credentials are intended for this local learning environment. For production, use secrets management and the least-privilege permissions appropriate for your deployment.

#### 3.2 Access the PMM Dashboard

Open:

```text
https://localhost:8443
```

After login, check the MySQL dashboards and **Query Analytics (QAN)** for the registered `local-docker-mysql` service.

#### 3.3 If MySQL Is Not Showing in the PMM Dashboard

If PMM Server is running but MySQL is not visible in the PMM dashboard, manually register MySQL from the `pmm-client` container:

```bash
docker exec -it pmm-client pmm-admin add mysql \
  --username=root \
  --password=<mysql_root_password> \
  --host=mysql \
  --port=3306 \
  --query-source=perfschema \
  local-docker-mysql
```
Register Redis/Valkey from the `pmm-client` container:

```bash
docker exec -it pmm-client pmm-admin add valkey \
  --host=redis \
  --port=6379 \
  local-docker-redis
```

Verify the registered services:

```bash
docker exec -it pmm-client pmm-admin list
```

You should see `local-docker-mysql` listed as a MySQL service. Refresh the PMM dashboard after registration.

> **Recommended:** If you have created the dedicated `pmm_monitor` account above, use it instead of `root`:
>
> ```bash
> docker exec -it pmm-client pmm-admin add mysql \
>   --username=pmm_monitor \
>   --password=pmm_password \
>   --host=mysql \
>   --port=3306 \
>   --query-source=perfschema \
>   local-docker-mysql
> ```

#### 3.4 Useful PMM Client Commands

```bash
# Check PMM agent status
docker exec -it pmm-client pmm-admin status

# List monitored services
docker exec -it pmm-client pmm-admin list

# View PMM client logs
docker logs -f pmm-client

# View PMM server logs
docker logs -f pmm-server
```

---

### 4. Prometheus & Grafana HTTP Monitoring

The project includes Prometheus and Grafana for Laravel HTTP observability. The existing `promphp/prometheus_client_php` Composer dependency is used with Redis storage so metrics survive across Laravel requests.

The monitoring flow is:

```text
Laravel HTTP requests
        |
        v
RecordHttpMetrics middleware
        |
        v
Redis-backed Prometheus metrics
        |
        v
/metrics
        |
        v
Prometheus
        |
        v
Grafana dashboard
```

The current Docker Compose setup runs MySQL, Redis, PMM, Prometheus, and Grafana in Docker. Laravel itself is run from the host, so Prometheus scrapes Laravel through `host.docker.internal:8000`.

#### 4.1 Start the monitoring containers

```bash
docker compose up -d mysql redis pmm-server pmm-client prometheus grafana
docker compose ps
```

Expected monitoring endpoints:

```text
Prometheus: http://localhost:9090
Grafana:    http://localhost:3000
Laravel:    http://localhost:8000
Metrics:    http://localhost:8000/metrics
```

#### 4.2 Start Laravel so Docker can scrape it

Because Laravel is running on the host, bind the development server to all interfaces:

```bash
php artisan serve --host=0.0.0.0 --port=8000
```

Do not use only `php artisan serve` for this setup because the default loopback binding can prevent the Prometheus container from reaching Laravel.

#### 4.3 Configure Prometheus metrics

The application registers `RecordHttpMetrics` as a global Laravel middleware. It records:

- `helpdesk_http_requests_total` — request count
- `helpdesk_http_request_duration_seconds` — HTTP latency histogram
- `helpdesk_http_requests_in_flight` — current requests being processed

Metric labels are intentionally limited to:

```text
method
route
status
```

Do not add user IDs, ticket IDs, email addresses, full URLs, or other unbounded values as Prometheus labels because they create high-cardinality time series.

Prometheus scrapes:

```text
http://host.docker.internal:8000/metrics
```

Check the target at:

```text
http://localhost:9090/targets
```

The `laravel` target should show `UP`.

You can also verify the endpoint directly from the host:

```bash
curl http://localhost:8000/metrics
```

You should see metrics such as:

```text
helpdesk_http_requests_total
helpdesk_http_request_duration_seconds_bucket
helpdesk_http_requests_in_flight
```

#### 4.4 Grafana dashboard

Grafana is provisioned automatically with:

- Prometheus datasource
- `HelpDesk - HTTP Monitoring` dashboard
- Request rate
- 5xx error rate
- p95 HTTP latency
- Request rate by route
- p95 latency by route
- HTTP status rate
- Requests in flight

Open:

```text
http://localhost:3000
```

The dashboard is loaded automatically from:

```text
grafana/dashboards/helpdesk-http.json
```

The Grafana datasource uses the Docker-internal URL:

```text
http://prometheus:9090
```

Do not use `http://localhost:9090` for the Grafana datasource because Grafana itself runs inside Docker.

#### 4.5 Useful PromQL queries

Requests per second:

```promql
sum(rate(helpdesk_http_requests_total[5m]))
```

Requests by route:

```promql
sum by (route) (rate(helpdesk_http_requests_total[5m]))
```

5xx requests per second:

```promql
sum(rate(helpdesk_http_requests_total{status=~"5.."}[5m]))
```

p95 latency:

```promql
histogram_quantile(
  0.95,
  sum by (le) (
    rate(helpdesk_http_request_duration_seconds_bucket[5m])
  )
)
```

p95 latency by route:

```promql
histogram_quantile(
  0.95,
  sum by (le, route) (
    rate(helpdesk_http_request_duration_seconds_bucket[5m])
  )
)
```

#### 4.6 Troubleshooting

If the Grafana dashboard is empty:

```bash
docker compose ps prometheus grafana
```

Then check Prometheus:

```bash
curl http://localhost:9090/-/healthy
curl http://localhost:9090/targets
```

Check Laravel metrics:

```bash
curl http://localhost:8000/metrics
```

Check Prometheus logs:

```bash
docker logs prometheus --tail 100
```

If the Prometheus `laravel` target is `DOWN`, verify Laravel is running with:

```bash
php artisan serve --host=0.0.0.0 --port=8000
```

### 5. Clone & Configure Environment

```bash
git clone https://github.com/your-org/laravel-help-desk.git
cd laravel-help-desk

cp .env.example .env
```

Ensure your `.env` contains the matching connection settings for the Docker containers:

```dotenv
APP_NAME=HelpDesk
APP_ENV=local
APP_KEY=
APP_DEBUG=true
APP_TIMEZONE=UTC
APP_URL=http://localhost:8000

# MySQL Docker Container Configuration
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=laravel_help_desk
DB_USERNAME=root
DB_PASSWORD=root_password
DB_ROOT_PASSWORD=root_password

# Redis Docker Container Configuration
REDIS_CLIENT=phpredis
REDIS_HOST=127.0.0.1
REDIS_PASSWORD=null
REDIS_PORT=6379
FORWARD_REDIS_PORT=6379

# Queue & Cache Drivers
CACHE_STORE=redis
QUEUE_CONNECTION=redis
SESSION_DRIVER=redis

# Laravel Reverb (WebSockets)
BROADCAST_CONNECTION=reverb
REVERB_APP_ID=980733
REVERB_APP_KEY=mmczeuguguo5yfidtgpn
REVERB_APP_SECRET=3vdxemwdwjhgaxe3hg20
REVERB_HOST="localhost"
REVERB_PORT=8080
REVERB_SCHEME=http

# Mailer Configuration
MAIL_MAILER=smtp
MAIL_HOST=127.0.0.1
MAIL_PORT=1025
MAIL_FROM_ADDRESS="support@helpdesk.test"
MAIL_FROM_NAME="HelpDesk Support"
```

---

### 4. Install Dependencies & Generate Keys
```bash
composer install
npm install

php artisan key:generate
php artisan jwt:secret
```

---

### 5. Run Migrations & Seed Test Data
```bash
# Runs all database migrations and seeds realistic domain test data
php artisan migrate:fresh --seed
```

#### Pre-Configured Seed Accounts (Password: `password`)
| Role | Email | Name | Access Level |
| :--- | :--- | :--- | :--- |
| **Admin** | `admin@example.com` | System Administrator | Full System & `/horizon` Access |
| **Agent** | `sarah.agent@example.com` | Sarah Connor (Senior Technical) | Agent Portal & Feed |
| **Agent** | `alex.agent@example.com` | Alex Murphy (Security & Auth) | Agent Portal & Feed |
| **Agent** | `david.agent@example.com` | David Miller (Billing Specialist) | Agent Portal & Feed |
| **Agent** | `elena.agent@example.com` | Elena Rostova (General Support) | Agent Portal & Feed |
| **Customer** | `john.customer@example.com` | John Doe | Customer Portal |
| **Customer** | `emily.customer@example.com` | Emily Blunt | Customer Portal |
| **Customer** | `bruce.wayne@example.com` | Bruce Wayne (VIP Account) | Customer Portal |

---

### 6. Background Queue Workers & Laravel Horizon

The application uses **Laravel Horizon** for production-grade queue supervision, auto-scaling, failure handling, and metrics.

1. **Start Horizon Supervisor:**
   ```bash
   php artisan horizon
   ```
   *Horizon automatically provisions workers across all active queues: `default`, `notifications`, `routing`, `maintenance`, and `broadcasts`.*

2. **Access the Horizon Dashboard:**
   - URL: `http://localhost:8000/horizon`
   - **Authorization:** Only authenticated users with the **`Admin`** role (e.g. `admin@example.com`) are granted access via the `viewHorizon` gate.

3. **Start the Console Scheduler (Snapshots & Maintenance):**
   ```bash
   php artisan schedule:work
   ```
   *Automatically takes Horizon metrics snapshots every 5 minutes (`horizon:snapshot`), escalates overdue SLA tickets, auto-closes inactive tickets, and prunes expired idempotency records.*

---

### 7. Build Assets & Start Application

```bash
# Build frontend assets
npm run build

# Start local backend server
php artisan serve
```

For interactive frontend development with live Hot Module Replacement (HMR):
```bash
npm run dev
```

For local WebSocket broadcasting, start Reverb:
```bash
php artisan reverb:start --debug
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
| **Attachments**| `POST /api/v1/attachments/chunk/init` | Chunked multi-part upload initialization | Authenticated |
| **Attachments**| `GET /api/v1/attachments/{id}/view` | Stream attachment preview / download | Authenticated |
| **Realtime** | `POST /broadcasting/auth` | Laravel Reverb WebSocket channel authentication | Authenticated |
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
- **130+ Tests, 699+ Assertions** across Unit and Feature test suites.
- **Patterns Test Suite (`tests/Feature/Patterns/`):** Full verification of State transitions, Strategy assignments, Chain of Responsibility routing, Command actions, Decorator pipeline, Events/Observers, and Specifications.
- **Idempotency Test Suite (`tests/Feature/Idempotency/`):** Tests replay caching, canonical hashing, distributed lock timeouts, and `IdempotencyInFlightException` handling.
- **Authorization Test Suite (`tests/Feature/Authorization/`):** Tests cross-tenant boundaries, role gates, and policy permissions.
- **Queue & Mail Test Suite (`tests/Feature/Jobs/`, `tests/Feature/Notifications/`):** Tests background jobs, unique job locks, mailables, and multi-stakeholder notification delivery.

---

## Documentation Index

Detailed architectural deep-dives and design specifications are maintained in the [`docs/`](docs/) directory:

- [`docs/API_ENDPOINTS.md`](docs/API_ENDPOINTS.md) — Comprehensive REST API endpoint reference, request/response JSON schemas, and query filters.
- [`docs/TICKET_ATTACHMENTS.md`](docs/TICKET_ATTACHMENTS.md) — Multi-part chunked upload architecture, size constraints, exponential backoff retries, local/S3 storage, and secure blob streaming.
- [`docs/REVERB_BROADCASTING.md`](docs/REVERB_BROADCASTING.md) — Real-time WebSocket broadcasting with Laravel Reverb, Redis Pub/Sub, JWT channel auth, presence tracking, and React Echo integration.
- [`docs/PROJECT.md`](docs/PROJECT.md) — Product requirements, user personas, and scope.
- [`docs/ARCHITECTURE.md`](docs/ARCHITECTURE.md) — System architecture, delivery layers, and multi-tenancy roadmap.
- [`docs/DDD_ARCHITECTURE_GUIDE.md`](docs/DDD_ARCHITECTURE_GUIDE.md) — Comprehensive guide on DDD Bounded Contexts, domain service providers, and directory structures.
- [`docs/DATABASE.md`](docs/DATABASE.md) — Relational database schema, table definitions, and indexing strategy.
- [`docs/PATTERNS.md`](docs/PATTERNS.md) — Architectural pattern catalog with code examples and implementations.
- [`docs/DECISIONS.md`](docs/DECISIONS.md) — Architecture Decision Records (ADRs).
- [`docs/IDEMPOTENCY_IMPLEMENTATION_NOTES.md`](docs/IDEMPOTENCY_IMPLEMENTATION_NOTES.md) — Idempotency subsystem architecture and concurrency model.
- [`docs/ROADMAP.md`](docs/ROADMAP.md) — Feature progress, completed milestones, and future phases.

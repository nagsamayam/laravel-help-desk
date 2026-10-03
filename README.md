# HelpDesk

A modern HelpDesk application built with **PHP 8.2+ / Laravel 13**,
**MySQL 8.4 LTS**, **Redis**, and a **React 19 Single Page Application
(SPA)** styled with **Tailwind CSS v4** and **shadcn/ui** components.

Developed exclusively for learning purposes and technical skill
refreshment, The project demonstrates Domain-Driven Design (DDD),
software design patterns, resilient transactional idempotency,
background job queues, multi-stakeholder email notifications, and
comprehensive test coverage.

------------------------------------------------------------------------

## Architecture Overview

``` text
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

------------------------------------------------------------------------

## Key Features

### 1. Robust Domain-Driven Architecture (DDD)

-   **Bounded Contexts:** Clear separation between `Domain/Ticket`,
    `Domain/Identity`, and `Domain/Audit`.
-   **Domain Service Providers:** Modular providers
    (`TicketServiceProvider`, `TicketEventServiceProvider`,
    `IdentityServiceProvider`, `AuditServiceProvider`,
    `NotificationServiceProvider`) maintain clean separation of
    concerns.
-   **Rich Domain Entities & Actions:** Business logic encapsulated in
    immutable DTOs and Action classes (`CreateTicketAction`,
    `UpdateTicketAction`, `ResolveTicketAction`, `CloseTicketAction`,
    `ReopenTicketAction`, `AssignTicketAction`,
    `AddTicketMessageAction`).

### 2. Applied Software Design Patterns

-   **State Pattern (`App\States\Ticket`):** Ticket state machine with
    strictly validated lifecycle transitions (`Open`, `InProgress`,
    `WaitingForCustomer`, `Resolved`, `Closed`).
-   **Strategy Pattern (`App\Strategies\Assignment`):** Plug-and-play
    agent assignment strategies (`RoundRobinAssignment`,
    `LeastBusyAgentAssignment`, `SkillBasedAssignment`) managed via
    `TicketAssignmentService`.
-   **Chain of Responsibility Pattern (`App\Routing`):** Configurable
    automated ticket routing pipeline (`VipRoutingRule` →
    `UrgentPriorityRoutingRule` → `CategoryRoutingRule` →
    `DefaultRoutingRule`).
-   **Command / Action Pattern (`App\Actions`):** Cohesive, reusable
    business use cases.
-   **Decorator Pattern (`App\Services\Notifications`):** Transparently
    layered notification sender pipeline
    (`LoggingNotificationSenderDecorator` →
    `MetricsNotificationSenderDecorator` →
    `RetryNotificationSenderDecorator` → `EmailNotificationSender`).
-   **Specification Pattern (`App\Specifications\Ticket`):** Composable
    in-memory and database query specifications
    (`OpenTicketSpecification`, `UrgentTicketSpecification`,
    `OverdueTicketSpecification`, `AssignedToAgentSpecification`, etc.)
    supporting `and`, `or`, and `not` boolean operators.
-   **Events / Observer Pattern (`App\Events\Tickets`,
    `App\Observers`):** Decoupled domain lifecycle events
    (`TicketCreated`, `TicketStatusChanged`, `TicketAssigned`,
    `TicketMessageAdded`) and `TicketObserver` for automated audit
    logging.

### 3. Production-Grade Transactional Idempotency

-   **Database-Authoritative Integrity:** MySQL composite unique
    constraints (`scope_type`, `scope_id`, `operation`, `key_hash`)
    guarantee single-execution semantics.
-   **Deterministic Payload Hashing:** SHA-256 canonical request hashing
    prevents key reuse with differing payloads
    (`IdempotencyConflictException` → HTTP 409).
-   **Concurrency & In-Flight Collision Guard:** Redis distributed locks
    optimize throughput; concurrent duplicate submissions waiting on
    processing gracefully return HTTP 409 with `Retry-After: 2`
    (`IdempotencyInFlightException`).
-   **Resource Metadata Persistence:** Captures `resource_type`
    (e.g. `'ticket'`) and `resource_id` alongside replayed headers and
    response envelopes.
-   **Automatic Background Maintenance:** Scheduled chunked pruning via
    `IdempotencyKey` and `php artisan model:prune` /
    `php artisan idempotency:prune`.

### 4. Background Queues & Asynchronous Jobs

-   **Queued Notification Dispatching:**
    `SendTicketNotificationListener` implements `ShouldQueue` on the
    `notifications` queue with backoff retries, rate limiting
    (`RateLimited('notifications')`), and exception throttling
    (`ThrottlesExceptions`).
-   **Domain Background Maintenance:**
    -   `EscalateOverdueTicketsJob`: Scans and escalates SLA-breached
        tickets with `ShouldBeUnique` deduplication.
    -   `AutoCloseResolvedTicketsJob`: Transitions stale resolved
        tickets to closed.
    -   `ProcessTicketRoutingJob`: Asynchronously routes tickets through
        the routing rules pipeline.
-   **Console Scheduling:** Scheduled execution in `routes/console.php`
    with `withoutOverlapping()` and `onOneServer()` protection.

### 5. Multi-Stakeholder Transactional Emails

-   **Styled Blade Mailables:** `TicketNotificationMail` delivers
    responsive HTML and plain-text emails.
-   **Role-Aware Routing:** Tailored notifications for Customers (ticket
    receipts, agent assignments, status updates), Support Admins (new
    ticket triage alerts), and Support Agents (assignment alerts,
    customer replies, internal notes).
-   **Security Boundary:** Internal staff notes are strictly isolated
    from customer notifications.

### 6. Modern React 19 Single Page Application (SPA)

-   **Frontend Stack:** React 19, Vite, Tailwind CSS v4, Lucide React
    icons, TanStack React Query v5, Zustand state store, and
    Sonner/Toast notifications.
-   **shadcn/ui Architecture:** Accessible UI primitives (`Button`,
    `Input`, `Select`, `Card`, `Modal`, `Table`, `Badge`, `Tabs`,
    `Toast`).
-   **Live Workflows:** Specification-based quick filtering (Urgent,
    Overdue SLA, Unassigned), interactive state transition dialogs,
    strategy-based & manual agent assignment, conversation feed with
    internal note toggle, status timeline, and JSON audit diff viewer.
-   **Automatic Client Resilience:** Axios interceptor with automatic
    UUIDv4 `Idempotency-Key` headers on mutating requests and automatic
    backoff retry on HTTP 409 in-flight collisions.

------------------------------------------------------------------------

## Technology Stack

  ------------------------------------------------------------------------
  Layer                               Technologies
  ----------------------------------- ------------------------------------
  **Backend Framework**               PHP 8.2+, Laravel 11/12

  **Database**                        MySQL 8.4 LTS (ACID source of truth)

  **Cache & Queues**                  Redis (Distributed locking,
                                      high-speed replay cache, async
                                      workers)

  **Queue Supervision**               Laravel Horizon (Real-time queue
                                      monitoring, auto-scaling, metrics
                                      dashboard)

  **Authentication**                  JWT Authentication
                                      (`php-open-source-saver/jwt-auth`)
                                      with RS256 / asymmetric key support

  **Frontend Framework**              React 19, Vite

  **Styling & Components**            Tailwind CSS v4, shadcn/ui component
                                      architecture, Lucide React

  **State Management**                TanStack React Query v5 (server
                                      state), Zustand (client session
                                      state)

  **Real-time Engine**                Laravel Reverb (WebSockets), Laravel
                                      Echo, Pusher-JS

  **Code Quality & Tests**            Pest / PHPUnit (106+ tests, 500+
                                      assertions), Laravel Pint
  ------------------------------------------------------------------------

------------------------------------------------------------------------

## Project Structure (Domain-Driven Design)

``` text
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

------------------------------------------------------------------------

## Installation & Setup

### 1. Prerequisites

-   **PHP 8.2+** with extensions (`pdo_mysql`, `redis`, `mbstring`,
    `bcmath`, `curl`)
-   **Composer 2.x**
-   **Node.js 20+** & **npm**
-   **Docker & Docker Compose** (for containerized MySQL 8.4 & Redis
    services)

------------------------------------------------------------------------

### 2. Start MySQL & Redis via Docker Compose

The project includes a ready-to-run `compose.yml` defining **MySQL 8.4
LTS** and **Redis (Alpine)** with health checks and persistent volume
storage.

1.  **Start the containers in detached mode:**

    ``` bash
    docker compose up -d
    ```

2.  **Verify container health and port bindings:**

    ``` bash
    docker compose ps
    ```

    *Expected output:*

    -   `mysql` listening on `0.0.0.0:3306->3306` (Status: `healthy`)
    -   `redis` listening on `0.0.0.0:6379->6379` (Status: `healthy`)

3.  **Useful Docker management commands:**

    ``` bash
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

------------------------------------------------------------------------

### 3. Clone & Configure Environment

``` bash
git clone https://github.com/your-org/laravel-help-desk.git
cd laravel-help-desk

cp .env.example .env
```

Ensure your `.env` contains the matching connection settings for the
Docker containers:

``` dotenv
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

------------------------------------------------------------------------

### 4. Install Dependencies & Generate Keys

``` bash
composer install
npm install

php artisan key:generate
php artisan jwt:secret
```

------------------------------------------------------------------------

### 5. Run Migrations & Seed Test Data

``` bash
# Runs all database migrations and seeds realistic domain test data
php artisan migrate:fresh --seed
```

#### Pre-Configured Seed Accounts (Password: `password`)

  ------------------------------------------------------------------------------------
  Role              Email                          Name              Access Level
  ----------------- ------------------------------ ----------------- -----------------
  **Admin**         `admin@example.com`            System            Full System &
                                                   Administrator     `/horizon` Access

  **Agent**         `sarah.agent@example.com`      Sarah Connor      Agent Portal &
                                                   (Senior           Feed
                                                   Technical)        

  **Agent**         `alex.agent@example.com`       Alex Murphy       Agent Portal &
                                                   (Security & Auth) Feed

  **Agent**         `david.agent@example.com`      David Miller      Agent Portal &
                                                   (Billing          Feed
                                                   Specialist)       

  **Agent**         `elena.agent@example.com`      Elena Rostova     Agent Portal &
                                                   (General Support) Feed

  **Customer**      `john.customer@example.com`    John Doe          Customer Portal

  **Customer**      `emily.customer@example.com`   Emily Blunt       Customer Portal

  **Customer**      `bruce.wayne@example.com`      Bruce Wayne (VIP  Customer Portal
                                                   Account)          
  ------------------------------------------------------------------------------------

------------------------------------------------------------------------

### 6. Background Queue Workers & Laravel Horizon

The application uses **Laravel Horizon** for production-grade queue
supervision, auto-scaling, failure handling, and metrics.

1.  **Start Horizon Supervisor:**

    ``` bash
    php artisan horizon
    ```

    *Horizon automatically provisions workers across all active queues:
    `default`, `notifications`, `routing`, `maintenance`, and
    `broadcasts`.*

2.  **Access the Horizon Dashboard:**

    -   URL: `http://localhost:8000/horizon`
    -   **Authorization:** Only authenticated users with the **`Admin`**
        role (e.g. `admin@example.com`) are granted access via the
        `viewHorizon` gate.

3.  **Start the Console Scheduler (Snapshots & Maintenance):**

    ``` bash
    php artisan schedule:work
    ```

    *Automatically takes Horizon metrics snapshots every 5 minutes
    (`horizon:snapshot`), escalates overdue SLA tickets, auto-closes
    inactive tickets, and prunes expired idempotency records.*

------------------------------------------------------------------------

### 7. Build Assets & Start Application

``` bash
# Build frontend assets
npm run build

# Start local backend server
php artisan serve
```

For interactive frontend development with live Hot Module Replacement
(HMR):

``` bash
npm run dev
```

For local WebSocket broadcasting, start Reverb:

``` bash
php artisan reverb:start --debug
```

------------------------------------------------------------------------

## Observability & Monitoring

This project includes a local observability stack for application
metrics, MySQL metrics, application/container logs, and database query
analysis.

### Observability Architecture

``` text
                                      ┌─────────────────────────────┐
                                      │           Grafana            │
                                      │   Dashboards + Explore       │
                                      └──────────────┬──────────────┘
                                                     │
                              ┌──────────────────────┴──────────────────────┐
                              │                                             │
                              ▼                                             ▼
                     ┌──────────────────┐                         ┌──────────────────┐
                     │    Prometheus    │                         │       Loki       │
                     │     Metrics      │                         │       Logs       │
                     └────────┬─────────┘                         └────────┬─────────┘
                              │                                            │
                 ┌────────────┴─────────────┐                  ┌───────────┴───────────┐
                 │                          │                  │                       │
                 ▼                          ▼                  ▼                       ▼
          Laravel /metrics          mysqld-exporter         Alloy               Laravel files
                 │                          │              Docker logs          storage/logs/*.log
                 │                          │
                 └──────────────────────────┘
                              │
                              ▼
                         MySQL 8.4

                         ┌──────────────────┐
                         │       PMM        │
                         │ Query Analytics  │
                         │ MySQL deep dive  │
                         └──────────────────┘
```

### Components

  ------------------------------------------------------------------------
  Component                                     Port Purpose
  --------------------- ---------------------------- ---------------------
  **Grafana**                                 `3000` Dashboards, PromQL,
                                                     LogQL and
                                                     visualization

  **Prometheus**                              `9090` Metrics collection
                                                     and time-series
                                                     storage

  **Loki**                                    `3100` Log aggregation and
                                                     LogQL queries

  **Grafana Alloy**                          `12345` Collects Docker logs
                                                     and Laravel log files

  **mysqld-exporter**                         `9104` Exposes MySQL server
                                                     metrics to Prometheus

  **PMM Server**                              `8443` MySQL Query Analytics
                                                     and deeper database
                                                     monitoring
  ------------------------------------------------------------------------

> **Important:** Laravel runs on the host with `php artisan serve`,
> while the monitoring components run in Docker. Prometheus therefore
> reaches Laravel through `host.docker.internal:8000`. Docker-to-Docker
> communication uses service names such as `prometheus:9090` and
> `loki:3100`.

------------------------------------------------------------------------

## Grafana

Open Grafana:

``` text
http://localhost:3000
```

Grafana is the visualization layer. It reads metrics from Prometheus and
logs from Loki.

### Prometheus data source

Configure:

``` text
http://prometheus:9090
```

Do not use `http://localhost:9090` from inside the Grafana container.

### Loki data source

Configure:

``` text
http://loki:3100
```

Do not use `http://localhost:3100` from inside the Grafana container.

### Useful LogQL queries

All Docker logs:

``` logql
{job="docker"}
```

MySQL logs:

``` logql
{service="mysql"}
```

Grafana logs:

``` logql
{service="grafana"}
```

Laravel logs:

``` logql
{service="laravel"}
```

Laravel errors:

``` logql
{service="laravel"} |= "ERROR"
```

Laravel exceptions:

``` logql
{service="laravel"} |= "Exception"
```

Laravel application log files:

``` logql
{service="laravel", filename=~".*laravel.*"}
```

------------------------------------------------------------------------

## Prometheus & Laravel Application Metrics

Laravel exposes:

``` text
http://localhost:8000/metrics
```

Current application metrics:

  ------------------------------------------------------------------------------
  Metric                                     Purpose
  ------------------------------------------ -----------------------------------
  `helpdesk_http_requests_total`             Total HTTP requests

  `helpdesk_http_request_duration_seconds`   HTTP request duration

  `helpdesk_http_requests_in_flight`         Requests currently being processed
  ------------------------------------------------------------------------------

Current labels are intentionally low-cardinality:

``` text
method
route
status
```

Avoid labels such as `user_id`, `ticket_id`, `email`, `request_id`, or
`full_url`.

Prometheus configuration:

``` yaml
- job_name: laravel
  metrics_path: /metrics
  static_configs:
    - targets:
        - host.docker.internal:8000
```

Prometheus:

``` text
http://localhost:9090
```

Targets:

``` text
http://localhost:9090/targets
```

Useful PromQL:

``` promql
rate(helpdesk_http_requests_total[5m])
```

``` promql
sum(rate(helpdesk_http_requests_total{status=~"5.."}[5m]))
```

------------------------------------------------------------------------

## MySQL Monitoring with mysqld-exporter

The MySQL metrics pipeline is:

``` text
MySQL 8.4
    ↓
mysqld-exporter :9104
    ↓
Prometheus :9090
    ↓
Grafana :3000
```

It provides server-level metrics such as connections, queries, slow
queries, threads, aborted connections, temporary tables and table locks.

### Docker Compose service

``` yaml
mysqld-exporter:
  image: prom/mysqld-exporter:latest
  container_name: mysqld-exporter
  restart: unless-stopped
  command:
    - "--config.my-cnf=/.my.cnf"
  volumes:
    - ./prometheus/mysql/.my.cnf:/.my.cnf:ro
  depends_on:
    mysql:
      condition: service_healthy
  networks:
    - monitoring
```

### Dedicated MySQL monitoring user

``` sql
CREATE USER 'prometheus'@'%'
IDENTIFIED BY 'strong-monitor-password';

GRANT PROCESS, REPLICATION CLIENT, SELECT
ON *.* TO 'prometheus'@'%';
```

Create:

``` text
prometheus/mysql/.my.cnf
```

``` ini
[client]
user=prometheus
password=strong-monitor-password
host=mysql
port=3306
```

Keep this file out of source control:

``` gitignore
prometheus/mysql/.my.cnf
```

### Prometheus target

``` yaml
- job_name: mysql
  static_configs:
    - targets:
        - mysqld-exporter:9104
```

Verify:

``` text
http://localhost:9090/targets
```

The `mysql` target should be `UP`.

Test the exporter:

``` bash
docker exec prometheus sh -c \
  'wget -qO- http://mysqld-exporter:9104/metrics' | head
```

Inspect MySQL metrics:

``` bash
docker exec prometheus sh -c \
  'wget -qO- http://mysqld-exporter:9104/metrics' \
  | grep '^mysql_' | head -20
```

If port `9104` is published, the exporter is also available at:

``` text
http://localhost:9104/metrics
```

Useful PromQL:

``` promql
mysql_up
```

``` promql
mysql_global_status_threads_connected
```

``` promql
rate(mysql_global_status_queries[5m])
```

``` promql
rate(mysql_global_status_slow_queries[5m])
```

``` promql
mysql_global_status_threads_running
```

``` promql
rate(mysql_global_status_aborted_connects[5m])
```

------------------------------------------------------------------------

## Loki

Loki is the log aggregation backend used by Grafana Alloy.

From the host:

``` text
http://localhost:3100
```

From another monitoring container:

``` text
http://loki:3100
```

### Loki service

``` yaml
loki:
  image: grafana/loki:latest
  container_name: loki
  restart: unless-stopped
  command:
    - "-config.file=/etc/loki/local-config.yaml"
  ports:
    - "3100:3100"
  volumes:
    - loki_data:/loki
  networks:
    - monitoring
```

Add:

``` yaml
volumes:
  loki_data:
```

### Health checks

``` bash
curl http://localhost:3100/ready
```

Expected:

``` text
ready
```

Build information:

``` bash
curl http://localhost:3100/loki/api/v1/status/buildinfo
```

> Use `/ready` for health checks. The root URL is not the primary
> readiness endpoint.

### Loki labels

Verify:

``` bash
curl -s http://localhost:3100/loki/api/v1/labels
```

The current setup exposes labels including:

``` text
container
filename
job
service
service_name
```

------------------------------------------------------------------------

## Grafana Alloy

Grafana Alloy collects both Docker container logs and Laravel log files.

### Alloy service

``` yaml
alloy:
  image: grafana/alloy:latest
  container_name: alloy
  restart: unless-stopped
  command:
    - "run"
    - "/etc/alloy/config.alloy"
    - "--server.http.listen-addr=0.0.0.0:12345"
  ports:
    - "12345:12345"
  volumes:
    - ./alloy/config.alloy:/etc/alloy/config.alloy:ro
    - /var/run/docker.sock:/var/run/docker.sock:ro
    - ./storage/logs:/var/log/laravel:ro
  depends_on:
    - loki
  networks:
    - monitoring
```

Alloy UI:

``` text
http://localhost:12345
```

### Docker log collection

``` alloy
discovery.docker "containers" {
  host = "unix:///var/run/docker.sock"
}

discovery.relabel "containers" {
  targets = discovery.docker.containers.targets

  rule {
    source_labels = ["__meta_docker_container_name"]
    regex         = "/(.*)"
    target_label  = "container"
  }

  rule {
    source_labels = ["__meta_docker_container_label_com_docker_compose_service"]
    target_label  = "service"
  }
}

loki.source.docker "containers" {
  host    = "unix:///var/run/docker.sock"
  targets = discovery.relabel.containers.output

  labels = {
    job = "docker",
  }

  forward_to = [loki.write.local.receiver]
}
```

### Laravel file collection

Because Laravel runs on the host, mount:

``` yaml
- ./storage/logs:/var/log/laravel:ro
```

Then configure Alloy:

``` alloy
local.file_match "laravel" {
  path_targets = [
    {
      __path__ = "/var/log/laravel/*.log",
      job      = "laravel",
      service  = "laravel",
    },
  ]
}

loki.source.file "laravel" {
  targets    = local.file_match.laravel.targets
  forward_to = [loki.write.local.receiver]
}
```

### Alloy → Loki

``` alloy
loki.write "local" {
  endpoint {
    url = "http://loki:3100/loki/api/v1/push"
  }
}
```

Restart after configuration changes:

``` bash
docker compose up -d --force-recreate alloy
```

Check:

``` bash
docker logs alloy --tail 100
```

Successful Laravel collection includes messages similar to:

``` text
start tailing file ... path=/var/log/laravel/laravel-2026-10-03.log
```

------------------------------------------------------------------------

## Laravel Log Verification

Generate a test log:

``` bash
php artisan tinker
```

``` php
\Log::info('Loki integration test');
```

``` text
exit
```

Then query Grafana Explore:

``` logql
{service="laravel"}
```

Or query Loki directly:

``` bash
curl -G -s \
  --data-urlencode 'query={service="laravel"}' \
  http://localhost:3100/loki/api/v1/query_range
```

------------------------------------------------------------------------

## PMM and MySQL Query Analytics

PMM complements Prometheus.

Use **Prometheus + mysqld-exporter** for time-series MySQL server
metrics.

Use **PMM Query Analytics** for investigating SQL workload, query
digests, latency and database behavior.

PMM is available at:

``` text
https://localhost:8443
```

The current PMM client configuration uses:

``` text
query source = perfschema
```

A practical workflow is:

``` text
Grafana/Prometheus
    ↓
Detect MySQL metric anomaly
    ↓
PMM Query Analytics
    ↓
Investigate SQL workload
```

------------------------------------------------------------------------

## Observability Troubleshooting

Check monitoring containers:

``` bash
docker compose ps
```

Check Loki:

``` bash
curl http://localhost:3100/ready
```

Test container-to-Loki connectivity:

``` bash
docker exec prometheus sh -c \
  'wget -qO- http://loki:3100/ready'
```

Check Alloy:

``` bash
docker logs alloy --tail 100
```

Check Loki labels:

``` bash
curl -s http://localhost:3100/loki/api/v1/labels
```

Check Prometheus targets:

``` text
http://localhost:9090/targets
```

Expected targets:

``` text
laravel       UP
mysql         UP
prometheus    UP
```

### Docker hostname rule

Inside Docker use:

``` text
Prometheus → http://prometheus:9090
Grafana    → http://prometheus:9090
Grafana    → http://loki:3100
Alloy      → http://loki:3100
Prometheus → http://mysqld-exporter:9104
Prometheus → http://host.docker.internal:8000
```

From the host/browser use:

``` text
Grafana    → http://localhost:3000
Prometheus → http://localhost:9090
Loki       → http://localhost:3100
Alloy      → http://localhost:12345
MySQL      → localhost:3306
Redis      → localhost:6379
PMM        → https://localhost:8443
```

------------------------------------------------------------------------

## Safe Monitoring Cleanup

Before cleanup:

``` bash
docker compose ps
docker ps --format "table {{.Names}}\t{{.Image}}\t{{.Status}}\t{{.Ports}}"
docker system df
docker volume ls
```

Reasonably safe cleanup candidates:

``` bash
docker container prune
docker image prune
docker network prune
```

Avoid these unless persistent data should intentionally be deleted:

``` bash
docker compose down -v
docker volume prune
```

Persistent volumes include:

``` text
mysql_data
redis_data
pmm_data
loki_data
```

Deleting them can remove MySQL, Redis, PMM, or Loki data.

------------------------------------------------------------------------

## Observability Quick Reference

  Task                 URL / Command
  -------------------- ----------------------------------------------------
  Grafana              `http://localhost:3000`
  Prometheus           `http://localhost:9090`
  Prometheus targets   `http://localhost:9090/targets`
  Loki                 `http://localhost:3100`
  Loki readiness       `curl http://localhost:3100/ready`
  Loki labels          `curl -s http://localhost:3100/loki/api/v1/labels`
  Alloy UI             `http://localhost:12345`
  MySQL exporter       `http://localhost:9104/metrics`
  Laravel metrics      `http://localhost:8000/metrics`
  PMM                  `https://localhost:8443`

------------------------------------------------------------------------

------------------------------------------------------------------------

## REST API Endpoints

All API endpoints are versioned under `/api/v1` and return standardized
JSON envelopes. For full endpoint documentation, request/response
schemas, validation rules, and query parameters, see
**[`docs/API_ENDPOINTS.md`](docs/API_ENDPOINTS.md)**.

### Quick Reference Summary

  --------------------------------------------------------------------------------------------------------
  Resource          Method & Path                               Description              Access
  ----------------- ------------------------------------------- ------------------------ -----------------
  **Auth**          `POST /api/v1/auth/register`                Register customer or     Public
                                                                agent account            

  **Auth**          `POST /api/v1/auth/login`                   Authenticate and obtain  Public
                                                                JWT Bearer token         

  **Auth**          `GET /api/v1/auth/me`                       Fetch authenticated user Authenticated
                                                                profile                  

  **Users**         `GET /api/v1/agents`                        List active support      Staff
                                                                agents                   

  **Tickets**       `GET /api/v1/tickets`                       Specification-filtered   Authenticated
                                                                ticket list              

  **Tickets**       `POST /api/v1/tickets`                      Create ticket            Authenticated
                                                                (`IdempotentRequest`     
                                                                protected)               

  **Tickets**       `GET /api/v1/tickets/{id}`                  Retrieve ticket details  Authenticated
                                                                with relations           

  **Tickets**       `PUT /api/v1/tickets/{id}`                  Update ticket metadata   Staff / Owner

  **Lifecycle**     `POST /api/v1/tickets/{id}/transition`      State pattern status     Staff
                                                                transition               

  **Lifecycle**     `POST /api/v1/tickets/{id}/resolve`         Resolve ticket with      Staff
                                                                notes                    

  **Lifecycle**     `POST /api/v1/tickets/{id}/close`           Close ticket             Staff / Owner

  **Lifecycle**     `POST /api/v1/tickets/{id}/reopen`          Reopen resolved/closed   Staff / Owner
                                                                ticket                   

  **Assignment**    `POST /api/v1/tickets/{id}/assign`          Strategy or manual agent Staff
                                                                assignment               

  **Routing**       `POST /api/v1/tickets/{id}/route`           Chain of Responsibility  Staff
                                                                routing pipeline         

  **Messages**      `GET /api/v1/tickets/{id}/messages`         List conversation        Authenticated
                                                                messages                 

  **Messages**      `POST /api/v1/tickets/{id}/messages`        Post reply or internal   Authenticated
                                                                staff note               

  **Attachments**   `POST /api/v1/attachments/chunk/init`       Chunked multi-part       Authenticated
                                                                upload initialization    

  **Attachments**   `GET /api/v1/attachments/{id}/view`         Stream attachment        Authenticated
                                                                preview / download       

  **Realtime**      `POST /broadcasting/auth`                   Laravel Reverb WebSocket Authenticated
                                                                channel authentication   

  **Audit**         `GET /api/v1/tickets/{id}/status-history`   Ticket status            Authenticated
                                                                progression timeline     

  **Audit**         `GET /api/v1/tickets/{id}/audit-logs`       Ticket-level mutation    Staff
                                                                audit diffs              

  **Audit**         `GET /api/v1/audit-logs`                    Browse global system     Admin
                                                                audit trail              
  --------------------------------------------------------------------------------------------------------

👉 *Full specifications, query filters, and sample JSON payloads:
**[`docs/API_ENDPOINTS.md`](docs/API_ENDPOINTS.md)***

The API collection for this project is committed directly to the
repository. You can download or import the file into Postman using the
link below:

👉 [Download Postman Collection](storage/postman/api_collection.json)

------------------------------------------------------------------------

## Testing & Quality Assurance

The application includes a comprehensive automated test suite testing
domain logic, pattern invariants, API contracts, authorization policies,
concurrency, and queue jobs.

``` bash
# Run all automated tests in parallel
php artisan test --parallel

# Verify code style compliance with Laravel Pint
./vendor/bin/pint --test

# Fix code style formatting automatically
./vendor/bin/pint
```

### Test Suite Highlights

-   **106+ Tests, 500+ Assertions** across Unit and Feature test suites.
-   **Patterns Test Suite (`tests/Feature/Patterns/`):** Full
    verification of State transitions, Strategy assignments, Chain of
    Responsibility routing, Command actions, Decorator pipeline,
    Events/Observers, and Specifications.
-   **Idempotency Test Suite (`tests/Feature/Idempotency/`):** Tests
    replay caching, canonical hashing, distributed lock timeouts, and
    `IdempotencyInFlightException` handling.
-   **Authorization Test Suite (`tests/Feature/Authorization/`):** Tests
    cross-tenant boundaries, role gates, and policy permissions.
-   **Queue & Mail Test Suite (`tests/Feature/Jobs/`,
    `tests/Feature/Notifications/`):** Tests background jobs, unique job
    locks, mailables, and multi-stakeholder notification delivery.

------------------------------------------------------------------------

## Documentation Index

Detailed architectural deep-dives and design specifications are
maintained in the [`docs/`](docs/) directory:

-   [`docs/API_ENDPOINTS.md`](docs/API_ENDPOINTS.md) --- Comprehensive
    REST API endpoint reference, request/response JSON schemas, and
    query filters.
-   [`docs/TICKET_ATTACHMENTS.md`](docs/TICKET_ATTACHMENTS.md) ---
    Multi-part chunked upload architecture, size constraints,
    exponential backoff retries, local/S3 storage, and secure blob
    streaming.
-   [`docs/REVERB_BROADCASTING.md`](docs/REVERB_BROADCASTING.md) ---
    Real-time WebSocket broadcasting with Laravel Reverb, Redis Pub/Sub,
    JWT channel auth, presence tracking, and React Echo integration.
-   [`docs/PROJECT.md`](docs/PROJECT.md) --- Product requirements, user
    personas, and scope.
-   [`docs/ARCHITECTURE.md`](docs/ARCHITECTURE.md) --- System
    architecture, delivery layers, and multi-tenancy roadmap.
-   [`docs/DDD_ARCHITECTURE_GUIDE.md`](docs/DDD_ARCHITECTURE_GUIDE.md)
    --- Comprehensive guide on DDD Bounded Contexts, domain service
    providers, and directory structures.
-   [`docs/DATABASE.md`](docs/DATABASE.md) --- Relational database
    schema, table definitions, and indexing strategy.
-   [`docs/PATTERNS.md`](docs/PATTERNS.md) --- Architectural pattern
    catalog with code examples and implementations.
-   [`docs/DECISIONS.md`](docs/DECISIONS.md) --- Architecture Decision
    Records (ADRs).
-   [`docs/IDEMPOTENCY_IMPLEMENTATION_NOTES.md`](docs/IDEMPOTENCY_IMPLEMENTATION_NOTES.md)
    --- Idempotency subsystem architecture and concurrency model.
-   [`docs/ROADMAP.md`](docs/ROADMAP.md) --- Feature progress, completed
    milestones, and future phases.

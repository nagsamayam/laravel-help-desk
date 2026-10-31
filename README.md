# HelpDesk

HelpDesk is a senior-level interview/project application built with Laravel, React, MySQL, Redis, and a production-style observability stack.

The project is designed to demonstrate practical backend, frontend, database, caching, API, idempotency, monitoring, logging, and troubleshooting skills.

## Technology Stack

### Backend

- PHP 8.5
- Laravel 13
- MySQL 8.4
- Redis
- Prometheus client for PHP

### Frontend

- React 19
- Tailwind CSS v4
- shadcn/ui

### Observability

- Prometheus
- Grafana
- Loki
- Grafana Alloy
- Percona Monitoring and Management (PMM)
- MySQL Exporter

## Application Architecture

The local development setup intentionally keeps Laravel running on the host while infrastructure services run in Docker.

```text
                         Browser
                            |
                            v
                    Laravel / React
                 http://localhost:8000
                            |
             +--------------+--------------+
             |                             |
             v                             v
           MySQL                         Redis
          :3306                          :6379
             |
             v
       MySQL Exporter
           :9104
             |
             v
         Prometheus
           :9090
          /      \
         v        v
     Grafana     metrics
       :3000
         |
         +------------------+
         |                  |
         v                  v
      Prometheus           Loki
                            :3100
                              ^
                              |
                           Alloy
                          :12345
                              |
                 +------------+------------+
                 |                         |
             Docker logs              Laravel logs
```

PMM is used separately for deeper MySQL/query analysis, especially Performance Schema based query analysis.

## Prerequisites

Install:

- PHP 8.5+
- Composer
- Node.js and npm
- Docker Desktop / Docker Engine
- Git
- MySQL client (optional but useful)

Verify:

```bash
php -v
composer --version
node -v
npm -v
docker --version
docker compose version
```

## Application Setup

Clone the project and enter the directory:

```bash
git clone <repository-url>
cd HelpDesk
```

Install PHP dependencies:

```bash
composer install
```

Install frontend dependencies:

```bash
npm install
```

Create the environment file:

```bash
cp .env.example .env
```

Generate the Laravel application key:

```bash
php artisan key:generate
```

## Environment

The Laravel application connects to MySQL and Redis using the Docker service names when Laravel itself is running inside Docker.

In the current development setup, Laravel runs on the host, so use the exposed Docker ports:

```env
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=helpdesk
DB_USERNAME=helpdesk
DB_PASSWORD=<your-password>

REDIS_HOST=127.0.0.1
REDIS_PORT=6379
```

Adjust credentials to match your local `compose.yml`.

Run migrations:

```bash
php artisan migrate
```

If seeders are available:

```bash
php artisan db:seed
```

## Run Laravel

Start Laravel on all interfaces so Docker services such as Prometheus can scrape it:

```bash
php artisan serve --host=0.0.0.0 --port=8000
```

Application:

```text
http://localhost:8000
```

Metrics endpoint:

```text
http://localhost:8000/metrics
```

The `/metrics` endpoint is intentionally excluded from HTTP request instrumentation to avoid the metrics endpoint measuring itself.

## Run the Frontend

Start the frontend development server:

```bash
npm run dev
```

Use the URL printed by Vite.

## Docker Infrastructure

Start the infrastructure services:

```bash
docker compose up -d
```

Check services:

```bash
docker compose ps
```

A useful overview is:

```bash
docker ps --format "table {{.Names}}\t{{.Image}}\t{{.Status}}\t{{.Ports}}"
```

Stop containers without deleting persistent volumes:

```bash
docker compose down
```

### Important

Do not routinely use:

```bash
docker compose down -v
```

and do not run:

```bash
docker volume prune
```

unless you intentionally want to remove persistent monitoring/database data.

The stack uses persistent volumes for services such as MySQL, Redis, PMM, Loki, and Grafana.

## MySQL

MySQL is exposed on:

```text
127.0.0.1:3306
```

Example connection:

```bash
mysql -h 127.0.0.1 -P 3306 -u helpdesk -p helpdesk
```

### MySQL Prometheus Exporter

The MySQL exporter runs as:

```text
prom/mysqld-exporter:latest
```

It connects to MySQL using a monitoring user.

Example exporter configuration:

```ini
[client]
user=prometheus
password=strong-monitor-password
host=mysql
port=3306
```

The monitoring user requires sufficient permissions for exporter metrics:

```sql
CREATE USER 'prometheus'@'%' IDENTIFIED BY 'strong-monitor-password';

GRANT PROCESS,
      REPLICATION CLIENT,
      SELECT
ON *.* TO 'prometheus'@'%';
```

Exporter endpoint:

```text
http://localhost:9104/metrics
```

Useful MySQL metrics include:

```promql
mysql_up

mysql_global_status_threads_connected

mysql_global_status_threads_running

rate(mysql_global_status_queries[5m])

rate(mysql_global_status_slow_queries[5m])

rate(mysql_global_status_aborted_connects[5m])
```

## Redis

Redis is exposed on:

```text
127.0.0.1:6379
```

Test it with:

```bash
redis-cli -h 127.0.0.1 -p 6379 ping
```

Expected:

```text
PONG
```

## Prometheus

Prometheus is available at:

```text
http://localhost:9090
```

The current scrape configuration includes Prometheus itself, Laravel, and MySQL Exporter.

Example:

```yaml
global:
  scrape_interval: 15s

scrape_configs:
  - job_name: prometheus
    static_configs:
      - targets:
          - prometheus:9090

  - job_name: laravel
    metrics_path: /metrics
    static_configs:
      - targets:
          - host.docker.internal:8000

  - job_name: mysql
    static_configs:
      - targets:
          - mysqld-exporter:9104
```

Verify targets:

```text
http://localhost:9090/targets
```

Laravel and MySQL exporter should show as `UP`.

## Laravel HTTP Metrics

Laravel exposes Prometheus metrics through:

```text
GET /metrics
```

The application records:

```text
helpdesk_http_requests_total
helpdesk_http_request_duration_seconds
helpdesk_http_requests_in_flight
```

Labels:

```text
method
route
status
```

### Request Counter

```promql
rate(helpdesk_http_requests_total[5m])
```

### Request Rate by Route

```promql
sum by (route) (
  rate(helpdesk_http_requests_total[5m])
)
```

### Top Routes

```promql
topk(
  10,
  sum by (route) (
    rate(helpdesk_http_requests_total[5m])
  )
)
```

### HTTP 5xx Rate

```promql
sum(
  rate(helpdesk_http_requests_total{status=~"5.."}[5m])
)
```

### HTTP 5xx Percentage

```promql
100 *
sum(rate(helpdesk_http_requests_total{status=~"5.."}[5m]))
/
clamp_min(sum(rate(helpdesk_http_requests_total[5m])), 1)
```

### P95 HTTP Latency

```promql
histogram_quantile(
  0.95,
  sum by (le) (
    rate(helpdesk_http_request_duration_seconds_bucket[5m])
  )
)
```

### P99 HTTP Latency

```promql
histogram_quantile(
  0.99,
  sum by (le) (
    rate(helpdesk_http_request_duration_seconds_bucket[5m])
  )
)
```

## Route Label Handling

The HTTP middleware intentionally avoids using raw URLs as Prometheus labels.

Do not use:

```text
/users/123
/users/124
/users/125
```

as separate metric labels because IDs can create unbounded Prometheus cardinality.

The middleware resolves the Laravel route URI and uses:

```text
unmatched
```

as a safe fallback.

The important behavior is:

1. Determine a safe initial route for the in-flight metric.
2. Call `$next($request)`.
3. Resolve the route again after Laravel has dispatched the request.
4. Record the final route and HTTP status.
5. Decrement the in-flight gauge using the same route label.

The middleware also protects against the literal placeholder:

```text
{fallbackPlaceholder}
```

so it is not intentionally emitted as a route label.

After changing the middleware, clear Laravel's cached configuration/routes:

```bash
php artisan optimize:clear
```

Then restart the Laravel server.

## Testing Route Metrics

Generate normal traffic:

```bash
curl http://127.0.0.1:8000/
```

Generate a nonexistent route:

```bash
curl http://127.0.0.1:8000/does-not-exist
```

Then query Prometheus:

```promql
topk(
  20,
  sum by (route) (
    rate(helpdesk_http_requests_total[5m])
  )
)
```

The important point is that new samples should not continue to be recorded with:

```text
route="{fallbackPlaceholder}"
```

If an old series remains visible temporarily, verify whether new requests are still increasing that series before assuming the middleware is still producing it.

If it continues increasing after restarting Laravel, search the application source for:

```text
fallbackPlaceholder
```

because the literal is then being introduced somewhere else.

## HTTP Status Handling

The middleware records the response status code for successful responses.

For exceptions, the middleware maps common Laravel/Symfony exceptions to appropriate HTTP status codes:

- Validation exception → `422`
- Authentication exception → `401`
- Authorization exception → `403`
- HTTP exception → its HTTP status
- Unknown exception → `500`

This keeps the Prometheus `status` label useful for error-rate queries.

## Grafana

Grafana is available at:

```text
http://localhost:3000
```

The provisioning setup uses:

### Prometheus

```text
http://prometheus:9090
```

UID:

```text
prometheus
```

### Loki

```text
http://loki:3100
```

UID:

```text
loki
```

Grafana therefore communicates with Prometheus and Loki through the Docker network rather than through `localhost`.

## Grafana Provisioning

The recommended directory structure is:

```text
HelpDesk/
├── grafana/
│   ├── dashboards/
│   │   └── helpdesk-observability-dashboard.json
│   └── provisioning/
│       ├── dashboards/
│       │   └── dashboards.yml
│       └── datasources/
│           └── datasources.yml
└── compose.yml
```

Datasource provisioning:

```yaml
apiVersion: 1

datasources:
  - name: Prometheus
    uid: prometheus
    type: prometheus
    access: proxy
    url: http://prometheus:9090
    isDefault: true
    editable: true

  - name: Loki
    uid: loki
    type: loki
    access: proxy
    url: http://loki:3100
    editable: true
```

Dashboard provisioning:

```yaml
apiVersion: 1

providers:
  - name: HelpDesk
    orgId: 1
    folder: HelpDesk
    type: file
    disableDeletion: false
    editable: true
    allowUiUpdates: true
    options:
      path: /var/lib/grafana/dashboards
      foldersFromFilesStructure: false
```

The Grafana container should mount:

```yaml
volumes:
  - grafana_data:/var/lib/grafana
  - ./grafana/dashboards:/var/lib/grafana/dashboards:ro
  - ./grafana/provisioning:/etc/grafana/provisioning:ro
```

After changing Grafana provisioning:

```bash
docker compose up -d --force-recreate grafana
```

No manual datasource mapping should be required.

## HelpDesk Observability Dashboard

The dashboard covers:

### Laravel / HTTP

- Request rate
- 5xx percentage
- P95 latency
- Requests in flight
- Request rate by status
- P50 latency
- P95 latency
- P99 latency
- Top routes
- Routes with 5xx responses

### MySQL

- MySQL availability
- Connected threads
- Running threads
- Queries per second
- Slow queries per second
- Threads connected/running
- Slow query trend
- Aborted connections

### Logs

- Recent Laravel logs
- Laravel errors
- Exceptions
- Critical/emergency messages

## Top Routes Grafana Panel

For the `Top Routes by Request Rate` table, use:

```promql
topk(
  10,
  sum by (route) (
    rate(helpdesk_http_requests_total[5m])
  )
)
```

Set:

```text
Format: Table
Instant: On
```

Do not use:

```text
legendFormat: {{route}}
```

for this table query. Grafana should display the Prometheus `route` label directly.

## Loki

Loki is available at:

```text
http://localhost:3100
```

Readiness check:

```bash
curl http://localhost:3100/ready
```

Expected:

```text
ready
```

## Grafana Alloy

Alloy collects:

1. Docker container logs.
2. Laravel log files.

Docker discovery uses the Docker socket:

```text
/var/run/docker.sock
```

Laravel logs are mounted read-only from:

```text
./storage/logs
```

into Alloy:

```text
/var/log/laravel
```

The Alloy configuration collects Laravel files matching:

```text
/var/log/laravel/*.log
```

Useful Loki labels include:

```text
job
service
container
filename
service_name
```

Examples in Grafana Explore:

```logql
{job="docker"}
```

```logql
{service="mysql"}
```

```logql
{service="grafana"}
```

```logql
{service="laravel"}
```

Laravel error logs can be explored with:

```logql
{service="laravel"} |= "ERROR"
```

Exceptions:

```logql
{service="laravel"} |= "Exception"
```

## PMM

Percona Monitoring and Management is used for deeper database observability.

PMM is especially useful for:

- Query Analytics
- Performance Schema
- Query digests
- Query latency
- Query frequency
- MySQL resource usage
- Database troubleshooting

Prometheus/MySQL Exporter metrics are aggregate metrics. They do not provide the full SQL text of every query.

For questions such as:

```text
Which SQL query deleted millions of rows?
Which query is slow?
Which query consumes the most time?
```

use PMM Query Analytics rather than relying only on Prometheus exporter metrics.

The configured query source is:

```text
perfschema
```

## Important Prometheus vs PMM Distinction

Prometheus answers questions such as:

```text
How many queries per second?
How many connections are active?
How many slow queries occurred?
Is MySQL up?
What is HTTP request latency?
```

PMM/QAN answers questions such as:

```text
Which SQL statements are running?
Which query digest is expensive?
Which SQL statement is slow?
How much time is spent on a query?
```

Use both systems together rather than expecting Prometheus to replace PMM Query Analytics.

## Useful Docker Commands

Show services:

```bash
docker compose ps
```

Show all containers:

```bash
docker ps --format "table {{.Names}}\t{{.Image}}\t{{.Status}}\t{{.Ports}}"
```

View logs:

```bash
docker compose logs -f grafana
```

```bash
docker compose logs -f prometheus
```

```bash
docker compose logs -f loki
```

```bash
docker compose logs -f alloy
```

```bash
docker compose logs -f mysql
```

Restart one service:

```bash
docker compose restart grafana
```

Recreate one service:

```bash
docker compose up -d --force-recreate grafana
```

## Safe Docker Cleanup

Inspect disk usage:

```bash
docker system df
```

Remove stopped containers:

```bash
docker container prune
```

Remove unused images:

```bash
docker image prune
```

Remove unused networks:

```bash
docker network prune
```

Check volumes before removing anything:

```bash
docker volume ls
```

Avoid deleting persistent volumes unless you intentionally want to destroy stored application/monitoring data.

## Troubleshooting

### Prometheus target is DOWN

Check:

```bash
docker compose ps
```

If Laravel is running on the host, Prometheus should scrape:

```text
host.docker.internal:8000
```

Test the endpoint from the host:

```bash
curl http://127.0.0.1:8000/metrics
```

Look for:

```text
helpdesk_http_requests_total
```

### Grafana cannot connect to Prometheus

Inside Docker, use:

```text
http://prometheus:9090
```

Do not configure Grafana's Docker datasource as:

```text
http://localhost:9090
```

### Grafana cannot connect to Loki

Use:

```text
http://loki:3100
```

not:

```text
http://localhost:3100
```

### Laravel logs do not appear in Loki

Check that the Laravel log directory exists:

```bash
ls -la storage/logs
```

Check Alloy:

```bash
docker compose logs -f alloy
```

Confirm the volume mapping:

```text
./storage/logs:/var/log/laravel:ro
```

Then query:

```logql
{service="laravel"}
```

### `{fallbackPlaceholder}` appears as a route

First clear Laravel caches:

```bash
php artisan optimize:clear
```

Restart Laravel:

```bash
php artisan serve --host=0.0.0.0 --port=8000
```

Generate fresh requests and inspect:

```promql
topk(
  20,
  sum by (route) (
    rate(helpdesk_http_requests_total[5m])
  )
)
```

The current middleware explicitly prevents the literal:

```text
{fallbackPlaceholder}
```

from being returned as the route label.

If the value continues to increase after restarting Laravel, search the project:

```bash
grep -R "fallbackPlaceholder" app routes config 2>/dev/null
```

### Old Prometheus series still appears

Prometheus retains historical time series for its configured retention period.

Therefore, an old:

```text
route="{fallbackPlaceholder}"
```

series can remain visible even after the application has been fixed.

Check whether new traffic is increasing it before treating it as an active problem.

## Development Workflow

Typical startup:

### Terminal 1 — Laravel

```bash
php artisan serve --host=0.0.0.0 --port=8000
```

### Terminal 2 — Frontend

```bash
npm run dev
```

### Terminal 3 — Infrastructure

```bash
docker compose up -d
```

Then open:

```text
Application: http://localhost:8000
Grafana:      http://localhost:3000
Prometheus:   http://localhost:9090
```

## Recommended Verification Checklist

After starting the stack:

```bash
docker compose ps
```

Verify:

- MySQL is running.
- Redis is running.
- Prometheus is running.
- Grafana is running.
- Loki is running.
- Alloy is running.
- PMM is running if included in the current compose configuration.

Then verify:

```bash
curl http://127.0.0.1:8000/metrics
```

Prometheus:

```text
http://localhost:9090/targets
```

Grafana:

```text
http://localhost:3000
```

Loki:

```bash
curl http://localhost:3100/ready
```

Finally check:

```promql
up
```

and:

```promql
topk(
  20,
  sum by (route) (
    rate(helpdesk_http_requests_total[5m])
  )
)
```

## Project Goals

This project is intended to demonstrate senior engineering practices rather than only framework knowledge.

Areas covered include:

- Laravel application architecture
- REST/API development
- React frontend development
- MySQL data modeling
- Redis caching
- Idempotent request handling
- Prometheus instrumentation
- HTTP latency and error monitoring
- MySQL exporter monitoring
- Grafana dashboards
- Loki log aggregation
- Grafana Alloy log collection
- PMM Query Analytics
- Docker-based local infrastructure
- Production-style troubleshooting
- Safe operational practices

## Notes

The observability stack is intentionally separated into complementary tools:

```text
Laravel
   |
   +--> Prometheus metrics
   |
   +--> Laravel logs --> Alloy --> Loki --> Grafana
   |
   +--> MySQL --> MySQL Exporter --> Prometheus
                  |
                  +--> PMM / Performance Schema for SQL analysis
```

This gives the project a practical monitoring setup similar to what would be expected in a production-oriented engineering environment.

# Idempotency Implementation Notes

## Status

**Completed:** Idempotency infrastructure with database authority and Redis optimization.

**Deferred:** HTTP-level `IdempotencyMiddleware`. Current ticket endpoints continue to integrate with `IdempotencyManager` directly until the middleware refactor is intentionally scheduled.

## Architecture

The project uses a dedicated `idempotency_keys` table rather than storing an idempotency key on `tickets`.

The database is the authoritative source of truth. Redis is an optimization layer for fast replay and concurrency coordination; correctness must not depend on Redis being available.

### Persistent record

`idempotency_keys` contains:

- `id`
- `scope_type`
- `scope_id`
- `operation`
- `key_hash`
- `request_hash`
- `response_status`
- `response_body`
- `resource_type`
- `resource_id`
- `expires_at`
- `completed_at`
- `created_at`

There is intentionally **no `updated_at`**.

Unique constraint: `scope_type + scope_id + operation + key_hash`

Index: `expires_at`

The raw `Idempotency-Key` value is never persisted. It is stored as SHA-256.

## Request semantics

The `Idempotency-Key` is a request header only. It must not be merged into the validated request payload.

Validation:

- 16–255 printable ASCII characters.
- Operation: non-empty, maximum 100 characters, matching `^[A-Za-z0-9._:-]+$`.

The request fingerprint includes the operation and canonicalized request payload. Canonicalization recursively sorts associative-array keys while preserving list order. The request fingerprint is SHA-256.

## Replay semantics

### First execution

The callback executes once and returns a fresh `IdempotencyResult` with `replayed = false`. The original HTTP status and response body are persisted.

### Same key + same request

The stored response is replayed with the original HTTP status/body and `replayed = true`. Ticket creation therefore remains HTTP `201` on replay.

### Same key + different request

`IdempotencyConflictException` is thrown. HTTP `409` is an HTTP-layer concern; the manager itself does not return HTTP status codes.

### Expired key

An expired idempotency record may be reused. Request-time expiration handling remains part of correctness; scheduled pruning is maintenance only.

## Scope

The idempotency namespace is determined by `scope_type`, `scope_id`, `operation`, and `key_hash`.

Current ticket creation uses the authenticated user as the scope, allowing the same idempotency key to be used independently by different users.

## Redis integration

`RedisIdempotencyStore` provides:

1. Fast completed-response cache.
2. Redis locking as a concurrency optimization.
3. Graceful fallback to the database when Redis is unavailable.

Redis is **never authoritative**.

Configuration:

```dotenv
IDEMPOTENCY_TTL_SECONDS=86400
IDEMPOTENCY_REDIS_ENABLED=true
IDEMPOTENCY_REDIS_STORE=redis
IDEMPOTENCY_REDIS_PREFIX=idempotency
IDEMPOTENCY_REDIS_LOCK_SECONDS=30
IDEMPOTENCY_REDIS_LOCK_WAIT_SECONDS=5
```

Tests can disable Redis with `IDEMPOTENCY_REDIS_ENABLED=false`.

### Important Redis design rules

- Cache expiration must not allow replay after the database considers the key expired. Cached entries should carry/validate the authoritative `expires_at` timestamp.
- Redis lock expiry is not a correctness mechanism. Database uniqueness and transactions remain authoritative.

## Current manager boundary

`IdempotencyManager` is intentionally HTTP-independent. It accepts scope, operation, key, request payload, and callback, and returns an `IdempotencyResult`.

It does **not** return HTTP responses or HTTP status codes.

## Ticket integration

Current ticket endpoints use the manager directly.

Create:

- first execution: HTTP `201`
- replay: HTTP `201`
- `Idempotency-Replayed: false|true`

Update/delete request fingerprints include the ticket identifier so the same key cannot be reused across different ticket resources.

For update/delete, resource resolution should happen inside the idempotent callback rather than relying on implicit route model binding before the idempotency layer executes. This allows a retry to replay the original successful response even when the underlying resource has since been deleted.

## Testing status

Covered behavior includes:

- callback executes once and completed response is replayed
- same key + different request raises `IdempotencyConflictException`
- scope and operation isolation
- expired key reuse
- same key across different authenticated users
- Redis-backed replay behavior
- operation continues when Redis is disabled/unavailable

For scope-isolation tests, `actingAs(..., 'api')` is preferred because the test is about authorization scope rather than JWT extraction. Do not use `refreshApplication()` as a JWT-state workaround because it resets the in-memory test application/database state.

## Important implementation hardening note

Before calling Redis locking fully production-hardened, review `RedisIdempotencyStore::withLock()` so Redis lock acquisition failures are handled separately from exceptions thrown by the protected callback.

A broad `catch (Throwable)` around a lock method that invokes the callback can accidentally interpret a business/application exception as a Redis failure and execute the callback a second time.

Safe design:

1. acquire/wait for the lock
2. if lock acquisition fails, fall back to the database path
3. execute the protected callback outside the Redis-error catch
4. release the lock in `finally`

## Deferred: IdempotencyMiddleware

A future `IdempotencyMiddleware` should decouple HTTP mechanics from the manager.

- `IdempotencyMiddleware`: HTTP headers, route operation, authenticated scope, response capture/replay.
- `IdempotencyManager`: idempotency algorithm and persistence orchestration.
- `RedisIdempotencyStore`: Redis cache/lock optimization.
- `IdempotencyKey`: persistent source of truth.
- Controller: endpoint orchestration.
- Action/service: ticket business logic.

The middleware should be opt-in for mutation endpoints rather than blindly applied to every route. Prefer stable named route operations such as `tickets.store`, `tickets.update`, and `tickets.destroy`.

The middleware refactor is intentionally postponed to avoid unnecessary file churn.

## Next development focus

Resume with the Ticket domain/business layer:

1. Ticket creation
2. Ticket update
3. Ticket deletion
4. Ticket lifecycle/state transitions
5. Assignment/routing
6. Ticket messages
7. Authorization/policies
8. Events/observers
9. SLA/notifications

## Environment/version baseline

- Laravel 13.33.0
- PHP 8.5.10
- Local MySQL: 9.3.0
- Redis: 8.10.2
- PestPHP 5
- JWT authentication via `php-open-source-saver/jwt-auth`
- JWT signing: asymmetric RSA / RS256
- REST API first
- API versioning: `/api/v1`
- Sanctum is not used

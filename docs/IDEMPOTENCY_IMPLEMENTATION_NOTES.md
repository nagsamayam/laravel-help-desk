# Idempotency Implementation Notes

## Subsystem Overview

The HelpDesk platform implements a database-authoritative, production-grade idempotency subsystem that guarantees single-execution semantics across all mutating API operations (`POST /api/v1/tickets`, `PUT /api/v1/tickets/{id}`, state transitions, and assignments).

---

## Architectural Principles

1. **Database as the Source of Truth:**
   - Uniqueness is enforced by MySQL composite unique index: `UNIQUE(scope_type, scope_id, operation, key_hash)`.
   - The database transaction wraps both the domain business action (`CreateTicketAction`, etc.) and the insertion/completion of the `IdempotencyKey` record atomically.
2. **Redis as Performance & Concurrency Optimization:**
   - `RedisIdempotencyStore` provides sub-millisecond completed response replays and distributed concurrency locking (`withLock`).
   - If Redis fails, times out, or is disabled, the system automatically falls back to database ACID guarantees with zero degradation of idempotency safety.
3. **Deterministic Payload Canonicalization:**
   - Request payloads are recursively sorted by keys (`ksort`) and encoded via SHA-256 (`request_hash`).
   - Attempting to reuse an active key with a differing payload triggers `IdempotencyConflictException` (HTTP 409 Conflict).
4. **In-Flight Concurrency Protection:**
   - If a duplicate request arrives while the initial request is still being processed, it encounters an in-flight check (`completed_at === null`), raising `IdempotencyInFlightException` (HTTP 409 Conflict with `Retry-After: 2` header).
5. **Decoupled HTTP Middleware:**
   - `IdempotentRequest` middleware inspects the `Idempotency-Key` header, manages execution inside `IdempotencyManager`, and attaches `Idempotency-Replayed: true|false` to responses.

---

## Schema & Column Definitions

`idempotency_keys` table definition:

```php
Schema::create('idempotency_keys', function (Blueprint $table): void {
    $table->id();
    $table->string('scope_type', 32);
    $table->string('scope_id', 64); // Supports int, UUID, ULID
    $table->string('operation', 100);
    $table->char('key_hash', 64);
    $table->char('request_hash', 64);
    $table->unsignedSmallInteger('response_status')->nullable();
    $table->json('response_body')->nullable();
    $table->json('response_headers')->nullable();
    $table->string('resource_type', 100)->nullable(); // e.g. 'ticket'
    $table->string('resource_id', 64)->nullable();
    $table->timestamp('expires_at');
    $table->timestamp('completed_at')->nullable();
    $table->timestamp('created_at')->nullable();

    $table->unique(
        ['scope_type', 'scope_id', 'operation', 'key_hash'],
        'idempotency_keys_scope_operation_key_unique',
    );
    $table->index('expires_at');
    $table->index(['resource_type', 'resource_id']);
});
```

---

## Replay & Header Semantics

| Request Scenario | Execution Path | Response Code | Headers Attached |
| :--- | :--- | :---: | :--- |
| **First Submission** | Executes business mutation inside DB transaction, persists response body and headers. | `201 Created` / `200 OK` | `Idempotency-Replayed: false` |
| **Exact Replay** | Hits Redis cache or DB, verifies payload hash match, replays stored payload. | Original (e.g. `201`) | `Idempotency-Replayed: true` |
| **Payload Conflict** | Detects mismatched `request_hash` for same active key. | `409 Conflict` | `Content-Type: application/json` |
| **In-Flight Collision** | Detects `completed_at === null` or lock acquisition timeout. | `409 Conflict` | `Retry-After: 2` |
| **Expired Key** | `expires_at < now()` allows the key to be reused for a fresh execution. | Fresh execution | `Idempotency-Replayed: false` |

---

## Frontend Integration & Automatic Backoff

The React 19 frontend incorporates an Axios interceptor that automatically attaches UUIDv4 idempotency keys and handles in-flight backoff retries:

```javascript
// resources/js/lib/api-client.js
apiClient.interceptors.request.use((config) => {
  if (['post', 'put', 'patch', 'delete'].includes(config.method?.toLowerCase() || '')) {
    if (!config.headers['Idempotency-Key']) {
      config.headers['Idempotency-Key'] = uuidv4();
    }
  }
  return config;
});

apiClient.interceptors.response.use(
  (response) => response,
  async (error) => {
    const { config, response } = error;
    if (response?.status === 409 && response?.data?.error === 'IDEMPOTENCY_IN_FLIGHT' && !config._retry) {
      config._retry = true;
      const retryAfter = (parseInt(response.headers['retry-after'], 10) || 2) * 1000;
      await new Promise((resolve) => setTimeout(resolve, retryAfter));
      return apiClient(config);
    }
    return Promise.reject(error);
  }
);
```

---

## Maintenance & Retention Pruning

Expired idempotency keys are non-destructively pruned using Laravel's `MassPrunable` trait and scheduled background jobs:

- **Command:** `php artisan model:prune` / `php artisan idempotency:prune`
- **Background Job:** `PruneExpiredIdempotencyKeysJob` runs daily with `ShouldBeUnique` deduplication.

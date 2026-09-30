<?php

declare(strict_types=1);

return [

    /*
    |--------------------------------------------------------------------------
    | Idempotency Key Retention
    |--------------------------------------------------------------------------
    |
    | How long a completed idempotency record remains reusable.
    |
    */

    'ttl_seconds' => (int) env(
        'IDEMPOTENCY_TTL_SECONDS',
        86_400,
    ),

    /*
    |--------------------------------------------------------------------------
    | In-Flight Retry-After Header
    |--------------------------------------------------------------------------
    |
    | Number of seconds the client should wait before retrying an in-flight
    | idempotent request (HTTP 409 Conflict with Retry-After header).
    |
    */

    'retry_after_seconds' => (int) env(
        'IDEMPOTENCY_RETRY_AFTER_SECONDS',
        2,
    ),

    /*
    |--------------------------------------------------------------------------
    | Redis
    |--------------------------------------------------------------------------
    |
    | Redis is an optimization layer only.
    |
    | MySQL remains the source of truth for idempotency. If Redis is
    | unavailable, idempotency must continue to work through the database.
    |
    */

    'redis' => [

        /*
        | Enable Redis cache/locking for idempotency.
        |
        */

        'enabled' => (bool) env(
            'IDEMPOTENCY_REDIS_ENABLED',
            true,
        ),

        /*
        | Laravel cache store to use for idempotency.
        |
        */

        'store' => env(
            'IDEMPOTENCY_REDIS_STORE',
            'redis',
        ),

        /*
        | Prefix used for all idempotency Redis keys.
        |
        */

        'prefix' => env(
            'IDEMPOTENCY_REDIS_PREFIX',
            'idempotency',
        ),

        /*
        |--------------------------------------------------------------------------
        | Distributed Lock
        |--------------------------------------------------------------------------
        |
        | The Redis lock reduces concurrent duplicate work.
        |
        | IMPORTANT:
        | This is NOT the correctness mechanism. The database unique
        | constraint remains authoritative.
        |
        */

        'lock_seconds' => (int) env(
            'IDEMPOTENCY_REDIS_LOCK_SECONDS',
            30,
        ),

        /*
        | Maximum time a request waits for another request holding
        | the same idempotency lock.
        |
        */

        'lock_wait_seconds' => (int) env(
            'IDEMPOTENCY_REDIS_LOCK_WAIT_SECONDS',
            5,
        ),

    ],

];

<?php

declare(strict_types=1);

namespace App\Infrastructure\Idempotency;

use Closure;
use Illuminate\Contracts\Cache\LockTimeoutException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Throwable;

final class RedisIdempotencyStore
{
    public function key(
        string $scopeType,
        string $scopeId,
        string $operation,
        string $keyHash,
    ): string {
        $identity = hash('sha256', implode("\0", [
            $scopeType,
            $scopeId,
            $operation,
            $keyHash,
        ]));

        return implode(':', [
            config('idempotency.redis.prefix', 'idempotency'),
            'result',
            $identity,
        ]);
    }

    public function get(string $key): ?array
    {
        if (! $this->enabled()) {
            return null;
        }

        try {
            $value = Cache::store($this->store())->get($key);

            return is_array($value) ? $value : null;
        } catch (Throwable $exception) {
            $this->reportFailure('get', $exception);

            return null;
        }
    }

    public function put(
        string $key,
        array $value,
        \DateTimeInterface $expiresAt,
    ): void {
        if (! $this->enabled()) {
            return;
        }

        if ($expiresAt->getTimestamp() <= now()->getTimestamp()) {
            return;
        }

        try {
            Cache::store($this->store())->put(
                $key,
                $value,
                $expiresAt,
            );
        } catch (Throwable $exception) {
            $this->reportFailure('put', $exception);
        }
    }

    /**
     * Redis locking is an optimization only.
     *
     * The database-backed idempotency flow remains authoritative.
     */
    public function withLock(
        string $key,
        Closure $callback,
    ): mixed {
        if (! $this->enabled()) {
            return $callback();
        }

        $lockSeconds = max(
            1,
            (int) config(
                'idempotency.redis.lock_seconds',
                30,
            ),
        );

        $waitSeconds = max(
            0,
            (int) config(
                'idempotency.redis.lock_wait_seconds',
                5,
            ),
        );

        $lock = Cache::store($this->store())
            ->lock($key, $lockSeconds);

        try {
            $lock->block($waitSeconds);
        } catch (LockTimeoutException $exception) {
            Log::debug(
                'Idempotency Redis lock timed out; falling back to the database source of truth.',
                [
                    'key' => $key,
                    'exception' => $exception::class,
                ],
            );

            return $callback();
        } catch (Throwable $exception) {
            $this->reportFailure('lock', $exception);

            return $callback();
        }

        try {
            return $callback();
        } finally {
            $lock->release();
        }
    }

    private function enabled(): bool
    {
        return (bool) config(
            'idempotency.redis.enabled',
            false,
        );
    }

    private function store(): string
    {
        return (string) config(
            'idempotency.redis.store',
            'redis',
        );
    }

    private function reportFailure(
        string $operation,
        Throwable $exception,
    ): void {
        Log::warning(
            'Idempotency Redis operation failed; continuing with database.',
            [
                'operation' => $operation,
                'exception' => $exception::class,
                'message' => $exception->getMessage(),
            ],
        );
    }
}

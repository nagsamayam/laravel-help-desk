<?php

declare(strict_types=1);

namespace App\Support\Idempotency;

use App\Exceptions\IdempotencyConflictException;
use App\Models\IdempotencyKey;
use Closure;
use Illuminate\Database\QueryException;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use JsonException;
use RuntimeException;

final class IdempotencyManager
{
    private const DUPLICATE_KEY_SQLSTATE = '23000';

    private const MYSQL_DUPLICATE_KEY = 1062;

    private const DEFAULT_TTL_SECONDS = 86_400;

    public function __construct(
        private readonly ?int $ttlSeconds = null,
    ) {}

    /**
     * Execute a database-backed operation exactly once per scoped idempotency key
     * for the retention period, and replay the original response thereafter.
     *
     * The callback must contain only database-transaction-safe work.
     * Do not perform external HTTP calls, emails, queues, or other irreversible
     * side effects inside it.
     */
    public function execute(
        string $scopeType,
        int|string $scopeId,
        string $operation,
        string $key,
        array $requestPayload,
        Closure $callback,
    ): IdempotencyResult {
        $key = $this->validateKey($key);
        $scopeId = (string) $scopeId;
        $operation = $this->validateOperation($operation);

        $keyHash = hash('sha256', $key);
        $requestHash = $this->requestHash($operation, $requestPayload);

        return DB::transaction(function () use (
            $scopeType,
            $scopeId,
            $operation,
            $keyHash,
            $requestHash,
            $callback,
        ): IdempotencyResult {
            $record = $this->reserve(
                scopeType: $scopeType,
                scopeId: $scopeId,
                operation: $operation,
                keyHash: $keyHash,
                requestHash: $requestHash,
            );

            /*
             * An existing completed record means this is a retry.
             *
             * The callback is deliberately NOT executed again.
             */
            if ($record instanceof IdempotencyResult) {
                return $record;
            }

            $result = $callback();

            if (! $result instanceof IdempotencyResult || $result->replayed) {
                throw new RuntimeException(
                    'The idempotent operation callback must return a fresh IdempotencyResult.'
                );
            }

            $record->update([
                'response_status' => $result->status,
                'response_body' => $result->body,
                'resource_type' => $result->resourceType,
                'resource_id' => $result->resourceId === null
                    ? null
                    : (string) $result->resourceId,
                'completed_at' => now(),
            ]);

            return $result;
        }, attempts: 5);
    }

    private function reserve(
        string $scopeType,
        string $scopeId,
        string $operation,
        string $keyHash,
        string $requestHash,
    ): IdempotencyKey|IdempotencyResult {
        try {
            return IdempotencyKey::query()->create([
                'scope_type' => $scopeType,
                'scope_id' => $scopeId,
                'operation' => $operation,
                'key_hash' => $keyHash,
                'request_hash' => $requestHash,
                'expires_at' => now()->addSeconds($this->ttlSeconds()),
            ]);
        } catch (QueryException $exception) {
            if (! $this->isDuplicateKey($exception)) {
                throw $exception;
            }

            $record = IdempotencyKey::query()
                ->where('scope_type', $scopeType)
                ->where('scope_id', $scopeId)
                ->where('operation', $operation)
                ->where('key_hash', $keyHash)
                ->firstOrFail();

            /*
             * Retention has expired, so this key may be reused.
             */
            if ($record->expires_at->isPast()) {
                $record->delete();

                return IdempotencyKey::query()->create([
                    'scope_type' => $scopeType,
                    'scope_id' => $scopeId,
                    'operation' => $operation,
                    'key_hash' => $keyHash,
                    'request_hash' => $requestHash,
                    'expires_at' => now()->addSeconds($this->ttlSeconds()),
                ]);
            }

            /*
             * Same key but different request payload.
             *
             * This is a client error and must never execute the callback.
             */
            if (! hash_equals($record->request_hash, $requestHash)) {
                throw new IdempotencyConflictException;
            }

            /*
             * Same key + same request.
             *
             * Because the idempotency record and the business operation are
             * committed in the same transaction, a committed record should
             * always be complete.
             */
            if (
                $record->completed_at === null
                || $record->response_status === null
                || $record->response_body === null
            ) {
                throw new RuntimeException(
                    'Idempotency record is incomplete.'
                );
            }

            return new IdempotencyResult(
                status: $record->response_status,
                body: $record->response_body,
                replayed: true,
                resourceType: $record->resource_type,
                resourceId: $record->resource_id,
            );
        }
    }

    private function requestHash(
        string $operation,
        array $payload,
    ): string {
        try {
            $canonical = json_encode(
                [
                    'operation' => $operation,
                    'payload' => $this->canonicalize($payload),
                ],
                JSON_THROW_ON_ERROR
                    | JSON_UNESCAPED_UNICODE
                    | JSON_UNESCAPED_SLASHES,
            );
        } catch (JsonException $exception) {
            throw new RuntimeException(
                'Unable to canonicalize the idempotency request.',
                previous: $exception,
            );
        }

        return hash('sha256', $canonical);
    }

    private function canonicalize(mixed $value): mixed
    {
        if (! is_array($value)) {
            return $value;
        }

        if (array_is_list($value)) {
            return array_map(
                fn (mixed $item): mixed => $this->canonicalize($item),
                $value,
            );
        }

        ksort($value);

        foreach ($value as $key => $item) {
            $value[$key] = $this->canonicalize($item);
        }

        return $value;
    }

    private function validateKey(string $key): string
    {
        $length = strlen($key);

        if (
            $length < 16
            || $length > 255
            || preg_match('/^[\x21-\x7E]+$/', $key) !== 1
        ) {
            throw new RuntimeException(
                'Idempotency-Key must contain 16-255 printable ASCII characters.'
            );
        }

        return $key;
    }

    private function validateOperation(string $operation): string
    {
        if (
            $operation === ''
            || strlen($operation) > 100
            || preg_match('/^[A-Za-z0-9._:-]+$/', $operation) !== 1
        ) {
            throw new RuntimeException(
                'Invalid idempotency operation.'
            );
        }

        return $operation;
    }

    private function ttlSeconds(): int
    {
        return $this->ttlSeconds
            ?? (int) config(
                'idempotency.ttl_seconds',
                self::DEFAULT_TTL_SECONDS,
            );
    }

    private function isDuplicateKey(QueryException $exception): bool
    {
        if ($exception instanceof UniqueConstraintViolationException) {
            return true;
        }

        return $exception->getCode() === self::DUPLICATE_KEY_SQLSTATE
            && ((int) ($exception->errorInfo[1] ?? 0) === self::MYSQL_DUPLICATE_KEY);
    }
}

<?php

declare(strict_types=1);

use App\Exceptions\IdempotencyConflictException;
use App\Exceptions\IdempotencyInFlightException;
use App\Models\IdempotencyKey;
use App\Support\Idempotency\IdempotencyManager;
use App\Support\Idempotency\IdempotencyResult;
use App\Support\Idempotency\RedisIdempotencyStore;
use Illuminate\Support\Facades\Cache;

beforeEach(function (): void {
    config([
        'idempotency.redis.enabled' => true,
        'idempotency.redis.store' => 'array',
    ]);

    Cache::store('array')->flush();
});

afterEach(function (): void {
    Cache::store('array')->flush();
});

it('caches a completed response in redis and replays it', function (): void {
    $manager = app(IdempotencyManager::class);

    $executions = 0;

    $callback = function () use (&$executions): IdempotencyResult {
        $executions++;

        return new IdempotencyResult(
            status: 200,
            body: [
                'data' => [
                    'id' => 123,
                    'subject' => 'Updated ticket',
                ],
            ],
            replayed: false,
            resourceType: 'ticket',
            resourceId: 123,
        );
    };

    $first = $manager->execute(
        scopeType: 'user',
        scopeId: 10,
        operation: 'tickets.update',
        key: 'redis-replay-test-01',
        requestPayload: [
            'ticket_id' => 123,
            'subject' => 'Updated ticket',
        ],
        callback: $callback,
    );

    $second = $manager->execute(
        scopeType: 'user',
        scopeId: 10,
        operation: 'tickets.update',
        key: 'redis-replay-test-01',
        requestPayload: [
            'ticket_id' => 123,
            'subject' => 'Updated ticket',
        ],
        callback: function (): IdempotencyResult {
            throw new LogicException(
                'The callback must not execute on Redis replay.',
            );
        },
    );

    expect($executions)->toBe(1)
        ->and($first->replayed)->toBeFalse()
        ->and($first->status)->toBe(200)
        ->and($second->replayed)->toBeTrue()
        ->and($second->status)->toBe(200)
        ->and($second->body)->toBe($first->body);
});

it('rejects a different request when the redis cache contains the same key', function (): void {
    $manager = app(IdempotencyManager::class);

    $manager->execute(
        scopeType: 'user',
        scopeId: 10,
        operation: 'tickets.update',
        key: 'redis-conflict-test-01',
        requestPayload: [
            'ticket_id' => 123,
            'subject' => 'Original subject',
        ],
        callback: function (): IdempotencyResult {
            return new IdempotencyResult(
                status: 200,
                body: [
                    'data' => [
                        'id' => 123,
                    ],
                ],
                replayed: false,
                resourceType: 'ticket',
                resourceId: 123,
            );
        },
    );

    expect(
        fn (): IdempotencyResult => $manager->execute(
            scopeType: 'user',
            scopeId: 10,
            operation: 'tickets.update',
            key: 'redis-conflict-test-01',
            requestPayload: [
                'ticket_id' => 123,
                'subject' => 'Different subject',
            ],
            callback: function (): IdempotencyResult {
                throw new LogicException(
                    'The callback must never execute for a conflict.',
                );
            },
        ),
    )->toThrow(IdempotencyConflictException::class);
});

it('continues to work without redis', function (): void {
    config([
        'idempotency.redis.enabled' => false,
    ]);

    $manager = app(IdempotencyManager::class);

    $executions = 0;

    $first = $manager->execute(
        scopeType: 'user',
        scopeId: 10,
        operation: 'tickets.create',
        key: 'redis-disabled-test-01',
        requestPayload: [
            'subject' => 'Printer issue',
        ],
        callback: function () use (&$executions): IdempotencyResult {
            $executions++;

            return new IdempotencyResult(
                status: 201,
                body: [
                    'data' => [
                        'id' => 123,
                    ],
                ],
                replayed: false,
                resourceType: 'ticket',
                resourceId: 123,
            );
        },
    );

    $second = $manager->execute(
        scopeType: 'user',
        scopeId: 10,
        operation: 'tickets.create',
        key: 'redis-disabled-test-01',
        requestPayload: [
            'subject' => 'Printer issue',
        ],
        callback: function (): IdempotencyResult {
            throw new LogicException(
                'The callback must not execute on database replay.',
            );
        },
    );

    expect($executions)->toBe(1)
        ->and($first->replayed)->toBeFalse()
        ->and($second->replayed)->toBeTrue()
        ->and($second->status)->toBe(201);
});

it('does not re-execute the callback when the callback throws', function (): void {
    $manager = app(IdempotencyManager::class);

    $executions = 0;

    try {
        $manager->execute(
            scopeType: 'user',
            scopeId: 999,
            operation: 'tests.callback_failure',
            key: 'callback-failure-'.bin2hex(random_bytes(8)),
            requestPayload: [
                'subject' => 'Failure test',
            ],
            callback: function () use (&$executions): IdempotencyResult {
                $executions++;

                throw new RuntimeException(
                    'Business operation failed.',
                );
            },
        );
    } catch (RuntimeException $exception) {
        expect($exception->getMessage())
            ->toBe('Business operation failed.');
    }

    expect($executions)->toBe(1);
});

it('falls back to the database when a redis cache entry is expired', function (): void {
    $manager = app(IdempotencyManager::class);
    $redis = app(RedisIdempotencyStore::class);

    $key = 'redis-expiry-test-01';
    $keyHash = hash('sha256', $key);

    $redisKey = $redis->key(
        scopeType: 'user',
        scopeId: '10',
        operation: 'tickets.create',
        keyHash: $keyHash,
    );

    $executions = 0;

    $first = $manager->execute(
        scopeType: 'user',
        scopeId: 10,
        operation: 'tickets.create',
        key: $key,
        requestPayload: [
            'subject' => 'Printer issue',
        ],
        callback: function () use (&$executions): IdempotencyResult {
            $executions++;

            return new IdempotencyResult(
                status: 201,
                body: [
                    'data' => [
                        'id' => 123,
                    ],
                ],
                replayed: false,
                resourceType: 'ticket',
                resourceId: 123,
            );
        },
    );

    $cached = Cache::store('array')->get($redisKey);

    expect($cached)->toBeArray();

    $cached['expires_at'] = now()
        ->subSecond()
        ->format(DATE_ATOM);

    Cache::store('array')->put(
        $redisKey,
        $cached,
        60,
    );

    $second = $manager->execute(
        scopeType: 'user',
        scopeId: 10,
        operation: 'tickets.create',
        key: $key,
        requestPayload: [
            'subject' => 'Printer issue',
        ],
        callback: function () use (&$executions): IdempotencyResult {
            $executions++;

            throw new LogicException(
                'The callback must not execute when the database record is complete.',
            );
        },
    );

    expect($first->replayed)->toBeFalse()
        ->and($second->replayed)->toBeTrue()
        ->and($executions)->toBe(1);
});

it('throws IdempotencyInFlightException when a database record is in-flight', function (): void {
    $manager = app(IdempotencyManager::class);
    $key = 'in-flight-test-01';

    // Simulate an incomplete/in-flight reservation row
    IdempotencyKey::query()->create([
        'scope_type' => 'user',
        'scope_id' => '10',
        'operation' => 'tickets.create',
        'key_hash' => hash('sha256', $key),
        'request_hash' => hash('sha256', json_encode([
            'operation' => 'tickets.create',
            'payload' => ['subject' => 'In flight test'],
        ])),
        'expires_at' => now()->addMinutes(10),
        'completed_at' => null,
    ]);

    expect(
        fn (): IdempotencyResult => $manager->execute(
            scopeType: 'user',
            scopeId: '10',
            operation: 'tickets.create',
            key: $key,
            requestPayload: [
                'subject' => 'In flight test',
            ],
            callback: fn (): IdempotencyResult => new IdempotencyResult(
                status: 201,
                body: ['data' => []],
                replayed: false,
            ),
        )
    )->toThrow(IdempotencyInFlightException::class);
});

it('handles string scope identifiers such as UUIDs and ULIDs', function (): void {
    $manager = app(IdempotencyManager::class);
    $uuidScope = '01923e20-9bf7-7973-82a1-cf5554f49419';

    $result = $manager->execute(
        scopeType: 'tenant',
        scopeId: $uuidScope,
        operation: 'invoices.create',
        key: 'uuid-scope-test-01',
        requestPayload: ['amount' => 5000],
        callback: fn (): IdempotencyResult => new IdempotencyResult(
            status: 201,
            body: ['data' => ['invoice_id' => 'inv_123']],
            replayed: false,
            resourceType: 'invoice',
            resourceId: 'inv_123',
        ),
    );

    expect($result->status)->toBe(201)
        ->and($result->replayed)->toBeFalse();

    $record = IdempotencyKey::query()
        ->where('scope_type', 'tenant')
        ->where('scope_id', $uuidScope)
        ->first();

    expect($record)->not->toBeNull()
        ->and($record->scope_id)->toBe($uuidScope);
});

it('persists and restores response headers on replay', function (): void {
    $manager = app(IdempotencyManager::class);
    $key = 'headers-test-01-abc';

    $first = $manager->execute(
        scopeType: 'user',
        scopeId: 10,
        operation: 'tickets.create',
        key: $key,
        requestPayload: ['subject' => 'Header test'],
        callback: fn (): IdempotencyResult => new IdempotencyResult(
            status: 201,
            body: ['data' => ['id' => 1]],
            replayed: false,
            headers: ['Location' => '/api/v1/tickets/1'],
        ),
    );

    $second = $manager->execute(
        scopeType: 'user',
        scopeId: 10,
        operation: 'tickets.create',
        key: $key,
        requestPayload: ['subject' => 'Header test'],
        callback: fn (): IdempotencyResult => throw new LogicException('Should not be called'),
    );

    expect($first->headers)->toBe(['Location' => '/api/v1/tickets/1'])
        ->and($second->replayed)->toBeTrue()
        ->and($second->headers)->toBe(['Location' => '/api/v1/tickets/1']);
});

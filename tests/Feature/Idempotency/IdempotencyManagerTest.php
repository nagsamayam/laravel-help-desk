<?php

declare(strict_types=1);

use App\Exceptions\IdempotencyConflictException;
use App\Support\Idempotency\IdempotencyManager;
use App\Support\Idempotency\IdempotencyResult;
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
        key: '0123456789abcdef',
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
        key: '0123456789abcdef',
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
        key: '0123456789abcdef',
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
            key: '0123456789abcdef',
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
        key: '0123456789abcdef',
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
        key: '0123456789abcdef',
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

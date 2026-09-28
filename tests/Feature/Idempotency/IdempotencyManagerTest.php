<?php

declare(strict_types=1);

use App\Exceptions\IdempotencyConflictException;
use App\Models\IdempotencyKey;
use App\Support\Idempotency\IdempotencyManager;
use App\Support\Idempotency\IdempotencyResult;

it('executes the callback once and replays the stored response', function (): void {
    $manager = app(IdempotencyManager::class);
    $executions = 0;

    $first = $manager->execute(
        scopeType: 'user',
        scopeId: 10,
        operation: 'tickets.create',
        key: '0123456789abcdef',
        requestPayload: ['subject' => 'Printer issue'],
        callback: function () use (&$executions): IdempotencyResult {
            $executions++;

            return new IdempotencyResult(
                status: 201,
                body: ['data' => ['id' => 123]],
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
        requestPayload: ['subject' => 'Printer issue'],
        callback: function (): IdempotencyResult {
            throw new LogicException(
                'The callback must not run on replay.'
            );
        },
    );

    expect($executions)->toBe(1)
        ->and($first->replayed)->toBeFalse()
        ->and($first->status)->toBe(201)
        ->and($first->body)->toBe([
            'data' => ['id' => 123],
        ])
        ->and($second->replayed)->toBeTrue()
        ->and($second->status)->toBe(201)
        ->and($second->body)->toBe([
            'data' => ['id' => 123],
        ])
        ->and($second->resourceType)->toBe('ticket')
        ->and($second->resourceId)->toBe('123');
});

it('rejects reuse of a key for a different request', function (): void {
    $manager = app(IdempotencyManager::class);

    $manager->execute(
        scopeType: 'user',
        scopeId: 10,
        operation: 'tickets.create',
        key: '0123456789abcdef',
        requestPayload: ['subject' => 'Printer issue'],
        callback: fn (): IdempotencyResult => new IdempotencyResult(
            status: 201,
            body: ['data' => ['id' => 123]],
            replayed: false,
        ),
    );

    expect(fn () => $manager->execute(
        scopeType: 'user',
        scopeId: 10,
        operation: 'tickets.create',
        key: '0123456789abcdef',
        requestPayload: ['subject' => 'Password reset'],
        callback: fn (): IdempotencyResult => new IdempotencyResult(
            status: 201,
            body: ['data' => ['id' => 456]],
            replayed: false,
        ),
    ))->toThrow(IdempotencyConflictException::class);
});

it('isolates the same key by scope and operation', function (): void {
    $manager = app(IdempotencyManager::class);

    $manager->execute(
        scopeType: 'user',
        scopeId: 10,
        operation: 'tickets.create',
        key: '0123456789abcdef',
        requestPayload: ['subject' => 'A'],
        callback: fn (): IdempotencyResult => new IdempotencyResult(
            status: 201,
            body: ['data' => ['id' => 1]],
            replayed: false,
        ),
    );

    $otherUser = $manager->execute(
        scopeType: 'user',
        scopeId: 11,
        operation: 'tickets.create',
        key: '0123456789abcdef',
        requestPayload: ['subject' => 'B'],
        callback: fn (): IdempotencyResult => new IdempotencyResult(
            status: 201,
            body: ['data' => ['id' => 2]],
            replayed: false,
        ),
    );

    $otherOperation = $manager->execute(
        scopeType: 'user',
        scopeId: 10,
        operation: 'tickets.update',
        key: '0123456789abcdef',
        requestPayload: ['subject' => 'A'],
        callback: fn (): IdempotencyResult => new IdempotencyResult(
            status: 200,
            body: ['data' => ['id' => 1]],
            replayed: false,
        ),
    );

    expect($otherUser->replayed)->toBeFalse()
        ->and($otherOperation->replayed)->toBeFalse()
        ->and($otherUser->status)->toBe(201)
        ->and($otherOperation->status)->toBe(200)
        ->and(IdempotencyKey::query()->count())->toBe(3);
});

it('allows a key to be reused after expiry', function (): void {
    $manager = new IdempotencyManager(ttlSeconds: 1);

    $first = $manager->execute(
        scopeType: 'user',
        scopeId: 10,
        operation: 'tickets.create',
        key: '0123456789abcdef',
        requestPayload: ['subject' => 'First'],
        callback: fn (): IdempotencyResult => new IdempotencyResult(
            status: 201,
            body: ['data' => ['id' => 1]],
            replayed: false,
        ),
    );

    IdempotencyKey::query()->update([
        'expires_at' => now()->subSecond(),
    ]);

    $second = $manager->execute(
        scopeType: 'user',
        scopeId: 10,
        operation: 'tickets.create',
        key: '0123456789abcdef',
        requestPayload: ['subject' => 'Second'],
        callback: fn (): IdempotencyResult => new IdempotencyResult(
            status: 201,
            body: ['data' => ['id' => 2]],
            replayed: false,
        ),
    );

    expect($first->replayed)->toBeFalse()
        ->and($second->replayed)->toBeFalse()
        ->and($second->status)->toBe(201)
        ->and($second->body)->toBe([
            'data' => ['id' => 2],
        ])
        ->and(IdempotencyKey::query()->count())->toBe(1);
});

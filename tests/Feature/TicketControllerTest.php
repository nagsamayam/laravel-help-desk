<?php

declare(strict_types=1);

use App\Enums\TicketPriority;
use App\Enums\TicketStatus;
use App\Models\Category;
use App\Models\Ticket;
use App\Models\User;
use PHPOpenSourceSaver\JWTAuth\Facades\JWTAuth;

it('creates a ticket and returns a non-replayed response', function (): void {
    $user = User::factory()->create();

    $category = Category::factory()->create();

    $token = JWTAuth::fromUser($user);

    $response = $this->withHeader(
        'Authorization',
        "Bearer {$token}",
    )->withHeader(
        'Idempotency-Key',
        '0123456789abcdef',
    )->postJson('/api/v1/tickets', [
        'category_id' => $category->id,
        'subject' => 'Printer issue',
        'description' => 'The printer is not working.',
        'priority' => TicketPriority::High,
    ]);

    $response
        ->assertCreated()
        ->assertHeader('Idempotency-Replayed', 'false');

    expect(Ticket::query()->count())->toBe(1);

    $ticket = Ticket::query()->first();

    expect($ticket)
        ->customer_id->toBe($user->id)
        ->category_id->toBe($category->id)
        ->subject->toBe('Printer issue')
        ->description->toBe('The printer is not working.')
        ->priority->toBe(TicketPriority::High)
        ->status->toBe(TicketStatus::Open);
});

it('replays the original response for the same idempotency key and request', function (): void {
    $user = User::factory()->create();

    $category = Category::factory()->create();

    $token = JWTAuth::fromUser($user);

    $headers = [
        'Authorization' => "Bearer {$token}",
        'Idempotency-Key' => '0123456789abcdef',
    ];

    $payload = [
        'category_id' => $category->id,
        'subject' => 'Printer issue',
        'description' => 'The printer is not working.',
        'priority' => TicketPriority::High,
    ];

    $firstResponse = $this
        ->withHeaders($headers)
        ->postJson('/api/v1/tickets', $payload);

    $firstResponse
        ->assertCreated()
        ->assertHeader('Idempotency-Replayed', 'false');

    $secondResponse = $this
        ->withHeaders($headers)
        ->postJson('/api/v1/tickets', $payload);

    $secondResponse
        ->assertCreated()
        ->assertHeader('Idempotency-Replayed', 'true')
        ->assertExactJson($firstResponse->json());

    expect(Ticket::query()->count())->toBe(1);
});

it('returns 409 when an idempotency key is reused with a different request', function (): void {
    $user = User::factory()->create();

    $category = Category::factory()->create();

    $token = JWTAuth::fromUser($user);

    $headers = [
        'Authorization' => "Bearer {$token}",
        'Idempotency-Key' => '0123456789abcdef',
    ];

    $this
        ->withHeaders($headers)
        ->postJson('/api/v1/tickets', [
            'category_id' => $category->id,
            'subject' => 'Printer issue',
            'description' => 'The printer is not working.',
            'priority' => TicketPriority::High,
        ])
        ->assertCreated();

    $response = $this
        ->withHeaders($headers)
        ->postJson('/api/v1/tickets', [
            'category_id' => $category->id,
            'subject' => 'Password reset',
            'description' => 'I need a password reset.',
            'priority' => TicketPriority::High,
        ]);

    $response
        ->assertStatus(409)
        ->assertJsonPath('error.message', 'The Idempotency-Key was already used with a different request.');

    expect(Ticket::query()->count())->toBe(1);
});

it('allows the same idempotency key for different authenticated users', function (): void {
    $firstUser = User::factory()->create();
    $secondUser = User::factory()->create();

    $category = Category::factory()->create();

    $key = '0123456789abcdef';

    $this
        ->actingAs($firstUser, 'api')
        ->withHeader('Idempotency-Key', $key)
        ->postJson('/api/v1/tickets', [
            'category_id' => $category->id,
            'subject' => 'First user ticket',
            'description' => 'First user description.',
            'priority' => TicketPriority::High->value,
        ])
        ->assertCreated()
        ->assertHeader('Idempotency-Replayed', 'false');

    $this
        ->actingAs($secondUser, 'api')
        ->withHeader('Idempotency-Key', $key)
        ->postJson('/api/v1/tickets', [
            'category_id' => $category->id,
            'subject' => 'Second user ticket',
            'description' => 'Second user description.',
            'priority' => TicketPriority::Low->value,
        ])
        ->assertCreated()
        ->assertHeader('Idempotency-Replayed', 'false');

    expect(Ticket::query()->count())->toBe(2);
});

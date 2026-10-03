<?php

declare(strict_types=1);

use App\Domain\Identity\Enums\Role;
use App\Domain\Identity\Models\User;
use App\Domain\Ticket\Enums\TicketPriority;
use App\Domain\Ticket\Enums\TicketStatus;
use App\Domain\Ticket\Events\TicketAssigned;
use App\Domain\Ticket\Events\TicketCreated;
use App\Domain\Ticket\Events\TicketMessageAdded;
use App\Domain\Ticket\Events\TicketStatusChanged;
use App\Domain\Ticket\Models\Category;
use App\Domain\Ticket\Models\Ticket;
use App\Domain\Ticket\Models\TicketMessage;
use Illuminate\Broadcasting\PresenceChannel;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Broadcast;

uses(RefreshDatabase::class);

test('ticket created event implements ShouldBroadcast and configures channels and payload', function (): void {
    $customer = User::factory()->create(['role' => Role::Customer]);
    $category = Category::factory()->create();

    $ticket = Ticket::factory()->create([
        'customer_id' => $customer->id,
        'category_id' => $category->id,
        'status' => TicketStatus::Open,
        'priority' => TicketPriority::High,
        'subject' => 'Production Database Slowdown',
    ]);

    $event = new TicketCreated($ticket);

    expect($event)->toBeInstanceOf(ShouldBroadcast::class)
        ->and($event->broadcastAs())->toBe('ticket.created');

    $channels = $event->broadcastOn();
    expect($channels)->toHaveCount(2)
        ->and($channels[0])->toBeInstanceOf(PrivateChannel::class)
        ->and($channels[0]->name)->toBe('private-agent.feed')
        ->and($channels[1])->toBeInstanceOf(PrivateChannel::class)
        ->and($channels[1]->name)->toBe('private-users.'.$customer->id);

    $payload = $event->broadcastWith();
    expect($payload)->toHaveKey('ticket')
        ->and($payload['ticket']['id'])->toBe($ticket->id)
        ->and($payload['ticket']['subject'])->toBe($ticket->subject)
        ->and($payload['ticket']['status'])->toBe(TicketStatus::Open->value)
        ->and($payload['ticket']['priority'])->toBe(TicketPriority::High->value);
});

test('ticket message added event broadcasts to presence channel for public messages and private channel for internal notes', function (): void {
    $customer = User::factory()->create(['role' => Role::Customer]);
    $agent = User::factory()->create(['role' => Role::Agent]);
    $category = Category::factory()->create();

    $ticket = Ticket::factory()->create([
        'customer_id' => $customer->id,
        'category_id' => $category->id,
    ]);

    $publicMessage = TicketMessage::factory()->create([
        'ticket_id' => $ticket->id,
        'user_id' => $customer->id,
        'message' => 'Customer reply',
        'is_internal' => false,
    ]);

    $publicEvent = new TicketMessageAdded($ticket, $publicMessage);
    $publicChannels = $publicEvent->broadcastOn();

    expect($publicEvent)->toBeInstanceOf(ShouldBroadcast::class)
        ->and($publicEvent->broadcastAs())->toBe('ticket.message.added')
        ->and($publicChannels)->toHaveCount(1)
        ->and($publicChannels[0])->toBeInstanceOf(PresenceChannel::class)
        ->and($publicChannels[0]->name)->toBe('presence-tickets.'.$ticket->id);

    $publicPayload = $publicEvent->broadcastWith();
    expect($publicPayload['ticket_id'])->toBe($ticket->id)
        ->and($publicPayload['message']['is_internal'])->toBeFalse()
        ->and($publicPayload['message']['message'])->toBe('Customer reply');

    $internalNote = TicketMessage::factory()->create([
        'ticket_id' => $ticket->id,
        'user_id' => $agent->id,
        'message' => 'Internal agent analysis note',
        'is_internal' => true,
    ]);

    $internalEvent = new TicketMessageAdded($ticket, $internalNote);
    $internalChannels = $internalEvent->broadcastOn();

    expect($internalChannels)->toHaveCount(2)
        ->and($internalChannels[0])->toBeInstanceOf(PrivateChannel::class)
        ->and($internalChannels[0]->name)->toBe('private-tickets.'.$ticket->id.'.internal')
        ->and($internalChannels[1])->toBeInstanceOf(PrivateChannel::class)
        ->and($internalChannels[1]->name)->toBe('private-agent.feed');

    $internalPayload = $internalEvent->broadcastWith();
    expect($internalPayload['message']['is_internal'])->toBeTrue();
});

test('ticket status changed event broadcasts on presence channel agent feed and customer channel', function (): void {
    $customer = User::factory()->create(['role' => Role::Customer]);
    $category = Category::factory()->create();

    $ticket = Ticket::factory()->create([
        'customer_id' => $customer->id,
        'category_id' => $category->id,
        'status' => TicketStatus::Resolved,
    ]);

    $event = new TicketStatusChanged($ticket, TicketStatus::InProgess, TicketStatus::Resolved);

    expect($event)->toBeInstanceOf(ShouldBroadcast::class)
        ->and($event->broadcastAs())->toBe('ticket.status.changed');

    $channels = $event->broadcastOn();
    expect($channels)->toHaveCount(3)
        ->and($channels[0])->toBeInstanceOf(PresenceChannel::class)
        ->and($channels[0]->name)->toBe('presence-tickets.'.$ticket->id)
        ->and($channels[1])->toBeInstanceOf(PrivateChannel::class)
        ->and($channels[1]->name)->toBe('private-agent.feed')
        ->and($channels[2])->toBeInstanceOf(PrivateChannel::class)
        ->and($channels[2]->name)->toBe('private-users.'.$customer->id);

    $payload = $event->broadcastWith();
    expect($payload['ticket_id'])->toBe($ticket->id)
        ->and($payload['previous_status'])->toBe(TicketStatus::InProgess->value)
        ->and($payload['new_status'])->toBe(TicketStatus::Resolved->value);
});

test('ticket assigned event broadcasts to presence channel agent feed and assigned agent', function (): void {
    $customer = User::factory()->create(['role' => Role::Customer]);
    $prevAgent = User::factory()->create(['role' => Role::Agent]);
    $newAgent = User::factory()->create(['role' => Role::Agent]);
    $category = Category::factory()->create();

    $ticket = Ticket::factory()->create([
        'customer_id' => $customer->id,
        'category_id' => $category->id,
        'assigned_to' => $newAgent->id,
    ]);

    $event = new TicketAssigned($ticket, $newAgent, $prevAgent->id);

    expect($event)->toBeInstanceOf(ShouldBroadcast::class)
        ->and($event->broadcastAs())->toBe('ticket.assigned');

    $channels = $event->broadcastOn();
    expect($channels)->toHaveCount(4)
        ->and($channels[0])->toBeInstanceOf(PresenceChannel::class)
        ->and($channels[0]->name)->toBe('presence-tickets.'.$ticket->id)
        ->and($channels[1])->toBeInstanceOf(PrivateChannel::class)
        ->and($channels[1]->name)->toBe('private-agent.feed')
        ->and($channels[2])->toBeInstanceOf(PrivateChannel::class)
        ->and($channels[2]->name)->toBe('private-users.'.$newAgent->id)
        ->and($channels[3])->toBeInstanceOf(PrivateChannel::class)
        ->and($channels[3]->name)->toBe('private-users.'.$prevAgent->id);

    $payload = $event->broadcastWith();
    expect($payload['ticket_id'])->toBe($ticket->id)
        ->and($payload['assigned_to'])->toBe($newAgent->id)
        ->and($payload['previous_agent_id'])->toBe($prevAgent->id);
});

test('broadcasting auth endpoint authorizes private and presence channels with jwt', function (): void {
    config([
        'broadcasting.default' => 'reverb',
        'broadcasting.connections.reverb.key' => 'test-key',
        'broadcasting.connections.reverb.secret' => 'test-secret',
        'broadcasting.connections.reverb.app_id' => 'test-app-id',
    ]);
    Broadcast::purge();
    require base_path('routes/channels.php');

    $customer1 = User::factory()->create(['role' => Role::Customer]);
    $customer2 = User::factory()->create(['role' => Role::Customer]);
    $agent = User::factory()->create(['role' => Role::Agent]);
    $admin = User::factory()->create(['role' => Role::Admin]);
    $category = Category::factory()->create();

    $ticket = Ticket::factory()->create([
        'customer_id' => $customer1->id,
        'category_id' => $category->id,
    ]);

    // 1. Customer can access own private user channel
    $this->actingAs($customer1, 'api')
        ->postJson('/broadcasting/auth', [
            'channel_name' => 'private-users.'.$customer1->id,
            'socket_id' => '1234.5678',
        ])
        ->assertOk()
        ->assertJsonStructure(['auth']);

    // 2. Stranger cannot access another user channel
    $this->actingAs($customer2, 'api')
        ->postJson('/broadcasting/auth', [
            'channel_name' => 'private-users.'.$customer1->id,
            'socket_id' => '1234.5678',
        ])
        ->assertForbidden();

    // 3. Customer can access own ticket presence channel
    $this->actingAs($customer1, 'api')
        ->postJson('/broadcasting/auth', [
            'channel_name' => 'presence-tickets.'.$ticket->id,
            'socket_id' => '1234.5678',
        ])
        ->assertOk()
        ->assertJsonStructure(['auth', 'channel_data']);

    // 4. Agent can access ticket presence channel
    $this->actingAs($agent, 'api')
        ->postJson('/broadcasting/auth', [
            'channel_name' => 'presence-tickets.'.$ticket->id,
            'socket_id' => '1234.5678',
        ])
        ->assertOk()
        ->assertJsonStructure(['auth', 'channel_data']);

    // 5. Stranger customer is forbidden from ticket presence channel
    $this->actingAs($customer2, 'api')
        ->postJson('/broadcasting/auth', [
            'channel_name' => 'presence-tickets.'.$ticket->id,
            'socket_id' => '1234.5678',
        ])
        ->assertForbidden();

    // 6. Agent can access internal ticket notes channel
    $this->actingAs($agent, 'api')
        ->postJson('/broadcasting/auth', [
            'channel_name' => 'private-tickets.'.$ticket->id.'.internal',
            'socket_id' => '1234.5678',
        ])
        ->assertOk()
        ->assertJsonStructure(['auth']);

    // 7. Customer is forbidden from internal ticket notes channel
    $this->actingAs($customer1, 'api')
        ->postJson('/broadcasting/auth', [
            'channel_name' => 'private-tickets.'.$ticket->id.'.internal',
            'socket_id' => '1234.5678',
        ])
        ->assertForbidden();

    // 8. Agent can access agent feed channel
    $this->actingAs($agent, 'api')
        ->postJson('/broadcasting/auth', [
            'channel_name' => 'private-agent.feed',
            'socket_id' => '1234.5678',
        ])
        ->assertOk()
        ->assertJsonStructure(['auth']);

    // 9. Customer is forbidden from agent feed channel
    $this->actingAs($customer1, 'api')
        ->postJson('/broadcasting/auth', [
            'channel_name' => 'private-agent.feed',
            'socket_id' => '1234.5678',
        ])
        ->assertForbidden();

    // 10. Admin can access agent feed channel
    $this->actingAs($admin, 'api')
        ->postJson('/broadcasting/auth', [
            'channel_name' => 'private-agent.feed',
            'socket_id' => '1234.5678',
        ])
        ->assertOk()
        ->assertJsonStructure(['auth']);
});

<?php

declare(strict_types=1);

use App\Domain\Identity\Enums\Role;
use App\Domain\Identity\Models\User;
use App\Domain\Ticket\Enums\TicketPriority;
use App\Domain\Ticket\Enums\TicketStatus;
use App\Domain\Ticket\Events\TicketAssigned;
use App\Domain\Ticket\Events\TicketMessageAdded;
use App\Domain\Ticket\Events\TicketStatusChanged;
use App\Domain\Ticket\Models\Category;
use App\Domain\Ticket\Models\Ticket;
use Illuminate\Support\Facades\Event;

it('allows listing and filtering tickets via specifications API', function (): void {
    $agent = User::factory()->create(['role' => Role::Agent]);
    $customer1 = User::factory()->create(['role' => Role::Customer]);
    $customer2 = User::factory()->create(['role' => Role::Customer]);

    $cat1 = Category::factory()->create(['name' => 'Billing']);
    $cat2 = Category::factory()->create(['name' => 'Technical']);

    $t1 = Ticket::factory()->create([
        'customer_id' => $customer1->id,
        'category_id' => $cat1->id,
        'status' => TicketStatus::Open,
        'priority' => TicketPriority::Urgent,
        'assigned_to' => $agent->id,
        'created_at' => now()->subDays(5),
    ]);

    $t2 = Ticket::factory()->create([
        'customer_id' => $customer1->id,
        'category_id' => $cat2->id,
        'status' => TicketStatus::InProgess,
        'priority' => TicketPriority::Low,
        'assigned_to' => null,
        'created_at' => now(),
    ]);

    $t3 = Ticket::factory()->create([
        'customer_id' => $customer2->id,
        'category_id' => $cat1->id,
        'status' => TicketStatus::Resolved,
        'priority' => TicketPriority::High,
        'assigned_to' => null,
        'created_at' => now(),
    ]);

    // Customer 1 only sees their own tickets
    $customerResponse = $this->actingAs($customer1, 'api')
        ->getJson('/api/v1/tickets');

    $customerResponse->assertOk()
        ->assertJsonCount(2, 'data');

    // Agent with specification filter: urgent
    $urgentResponse = $this->actingAs($agent, 'api')
        ->getJson('/api/v1/tickets?urgent=1');

    $urgentResponse->assertOk()
        ->assertJsonCount(2, 'data');

    $exactPriorityResponse = $this->actingAs($agent, 'api')
        ->getJson('/api/v1/tickets?priority=URGENT');

    $exactPriorityResponse->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.id', $t1->id);

    // Agent with specification filter: unassigned & status
    $unassignedResponse = $this->actingAs($agent, 'api')
        ->getJson('/api/v1/tickets?unassigned=1&status=IN_PROGRESS');

    $unassignedResponse->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.id', $t2->id);

    // Agent with overdue filter
    $overdueResponse = $this->actingAs($agent, 'api')
        ->getJson('/api/v1/tickets?overdue=1&overdue_days=3');

    $overdueResponse->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.id', $t1->id);
});

it('handles ticket lifecycle transitions via state and action endpoints', function (): void {
    Event::fake([TicketStatusChanged::class]);

    $agent = User::factory()->create(['role' => Role::Agent]);
    $customer = User::factory()->create(['role' => Role::Customer]);
    $ticket = Ticket::factory()->create([
        'customer_id' => $customer->id,
        'status' => TicketStatus::Open,
    ]);

    // Agent transitions to in_progress
    $this->actingAs($agent, 'api')
        ->postJson("/api/v1/tickets/{$ticket->id}/transition", [
            'status' => TicketStatus::InProgess->value,
        ])
        ->assertOk()
        ->assertJsonPath('data.status', TicketStatus::InProgess->value);

    // Agent resolves the ticket
    $this->actingAs($agent, 'api')
        ->postJson("/api/v1/tickets/{$ticket->id}/resolve")
        ->assertOk()
        ->assertJsonPath('data.status', TicketStatus::Resolved->value);

    // Customer closes their resolved ticket
    $this->actingAs($customer, 'api')
        ->postJson("/api/v1/tickets/{$ticket->id}/close")
        ->assertOk()
        ->assertJsonPath('data.status', TicketStatus::Closed->value);

    // Customer reopens their closed ticket
    $this->actingAs($customer, 'api')
        ->postJson("/api/v1/tickets/{$ticket->id}/reopen")
        ->assertOk()
        ->assertJsonPath('data.status', TicketStatus::Open->value);

    Event::assertDispatched(TicketStatusChanged::class);
});

it('rejects invalid state transitions via API with 422 Unprocessable Entity', function (): void {
    $agent = User::factory()->create(['role' => Role::Agent]);
    $ticket = Ticket::factory()->create([
        'status' => TicketStatus::Open,
    ]);

    // Direct transition from Open to Resolved is disallowed in state machine
    $response = $this->actingAs($agent, 'api')
        ->postJson("/api/v1/tickets/{$ticket->id}/transition", [
            'status' => TicketStatus::Resolved->value,
        ]);

    $response->assertStatus(422)
        ->assertJsonPath('error.code', 'UNPROCESSABLE_ENTITY');
});

it('assigns tickets directly or using assignment strategies', function (): void {
    Event::fake([TicketAssigned::class]);

    $admin = User::factory()->create(['role' => Role::Admin]);
    $agent1 = User::factory()->create([
        'first_name' => 'Agent',
        'last_name' => 'One',
        'role' => Role::Agent,
    ]);
    $agent2 = User::factory()->create([
        'first_name' => 'Agent',
        'last_name' => 'Two',
        'role' => Role::Agent,
    ]);
    $category = Category::factory()->create();

    $ticket = Ticket::factory()->create([
        'category_id' => $category->id,
        'assigned_to' => null,
    ]);

    // Direct assignment to agent1
    $this->actingAs($admin, 'api')
        ->postJson("/api/v1/tickets/{$ticket->id}/assign", [
            'agent_id' => $agent1->id,
        ])
        ->assertOk()
        ->assertJsonPath('data.assigned_to', $agent1->id);

    expect($ticket->refresh()->assigned_to)->toBe($agent1->id);

    // Strategy-based assignment (least_busy)
    // Create an unassigned ticket
    $ticket2 = Ticket::factory()->create([
        'category_id' => $category->id,
        'assigned_to' => null,
    ]);

    $this->actingAs($admin, 'api')
        ->postJson("/api/v1/tickets/{$ticket2->id}/assign", [
            'strategy' => 'least_busy',
        ])
        ->assertOk();

    // Agent Two had 0 active tickets, so agent2 is selected
    expect($ticket2->refresh()->assigned_to)->toBe($agent2->id);

    // Direct assignment using assigned_to parameter alias on a third ticket
    $ticket3 = Ticket::factory()->create([
        'category_id' => $category->id,
        'assigned_to' => null,
    ]);

    $this->actingAs($admin, 'api')
        ->postJson("/api/v1/tickets/{$ticket3->id}/assign", [
            'assigned_to' => $agent1->id,
        ])
        ->assertOk()
        ->assertJsonPath('data.assigned_to', $agent1->id);

    expect($ticket3->refresh()->assigned_to)->toBe($agent1->id);

    Event::assertDispatched(TicketAssigned::class);
});

it('routes tickets via chain of responsibility endpoint', function (): void {
    $agent = User::factory()->create(['role' => Role::Agent]);
    $vipCustomer = User::factory()->create([
        'first_name' => 'VIP',
        'last_name' => 'Client',
        'email' => 'client@vip.com',
        'role' => Role::Customer,
    ]);

    $ticket = Ticket::factory()->create([
        'customer_id' => $vipCustomer->id,
        'subject' => 'Regular inquiry',
        'priority' => TicketPriority::Low,
    ]);

    $response = $this->actingAs($agent, 'api')
        ->postJson("/api/v1/tickets/{$ticket->id}/route", [
            'persist' => true,
        ]);

    $response->assertOk()
        ->assertJsonPath('data.matched_rule', 'VIP Rule')
        ->assertJsonPath('data.priority', TicketPriority::Urgent->value);

    expect($ticket->refresh()->priority)->toBe(TicketPriority::Urgent);
});

it('manages conversation messages with internal note visibility rules and events', function (): void {
    Event::fake([TicketMessageAdded::class]);

    $customer = User::factory()->create(['role' => Role::Customer]);
    $agent = User::factory()->create(['role' => Role::Agent]);

    $ticket = Ticket::factory()->create([
        'customer_id' => $customer->id,
    ]);

    // Customer adds public message using 'body' parameter alias
    $this->actingAs($customer, 'api')
        ->postJson("/api/v1/tickets/{$ticket->id}/messages", [
            'body' => 'Hello, I have an issue with login.',
        ])
        ->assertCreated()
        ->assertJsonPath('data.is_internal', false)
        ->assertJsonPath('data.message', 'Hello, I have an issue with login.')
        ->assertJsonPath('data.body', 'Hello, I have an issue with login.');

    // Agent adds internal note
    $this->actingAs($agent, 'api')
        ->postJson("/api/v1/tickets/{$ticket->id}/messages", [
            'message' => 'Internal investigation: check user auth record.',
            'is_internal' => true,
        ])
        ->assertCreated()
        ->assertJsonPath('data.is_internal', true);

    // Agent adds public reply
    $this->actingAs($agent, 'api')
        ->postJson("/api/v1/tickets/{$ticket->id}/messages", [
            'message' => 'We are looking into this for you.',
            'is_internal' => false,
        ])
        ->assertCreated();

    // Customer views messages (should see only 2 public messages, not the internal note)
    $customerMessagesResponse = $this->actingAs($customer, 'api')
        ->getJson("/api/v1/tickets/{$ticket->id}/messages");

    $customerMessagesResponse->assertOk()
        ->assertJsonCount(2, 'data');

    // Agent views messages (should see all 3 messages)
    $agentMessagesResponse = $this->actingAs($agent, 'api')
        ->getJson("/api/v1/tickets/{$ticket->id}/messages");

    $agentMessagesResponse->assertOk()
        ->assertJsonCount(3, 'data');

    Event::assertDispatched(TicketMessageAdded::class);
});

it('allows agents and admins to fetch users and agents lists', function (): void {
    $admin = User::factory()->create(['role' => Role::Admin]);
    $agent = User::factory()->create(['role' => Role::Agent]);
    $customer = User::factory()->create(['role' => Role::Customer]);

    // Admin can fetch agents
    $response = $this->actingAs($admin, 'api')
        ->getJson('/api/v1/agents');

    $response->assertOk()
        ->assertJsonFragment(['email' => $admin->email])
        ->assertJsonFragment(['email' => $agent->email])
        ->assertJsonMissing(['email' => $customer->email]);

    // Admin can fetch users
    $this->actingAs($admin, 'api')
        ->getJson('/api/v1/users')
        ->assertOk()
        ->assertJsonFragment(['email' => $customer->email]);

    // Customer is forbidden from fetching agents or users
    $this->actingAs($customer, 'api')
        ->getJson('/api/v1/agents')
        ->assertForbidden();
});

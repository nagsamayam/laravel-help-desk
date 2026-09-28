<?php

declare(strict_types=1);

use App\Enums\Role;
use App\Enums\TicketPriority;
use App\Enums\TicketStatus;
use App\Models\Category;
use App\Models\Ticket;
use App\Models\User;
use App\Specifications\Ticket\AssignedToAgentSpecification;
use App\Specifications\Ticket\CustomerTicketsSpecification;
use App\Specifications\Ticket\OpenTicketSpecification;
use App\Specifications\Ticket\OverdueTicketSpecification;
use App\Specifications\Ticket\PriorityTicketSpecification;
use App\Specifications\Ticket\StatusTicketSpecification;
use App\Specifications\Ticket\UnassignedTicketSpecification;
use App\Specifications\Ticket\UrgentTicketSpecification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;

uses(RefreshDatabase::class);

test('status and open ticket specifications match in memory and in query builder', function (): void {
    $category = Category::factory()->create();
    $customer = User::factory()->create(['role' => Role::Customer]);

    $openTicket = Ticket::factory()->create([
        'status' => TicketStatus::Open,
        'category_id' => $category->id,
        'customer_id' => $customer->id,
    ]);

    $closedTicket = Ticket::factory()->create([
        'status' => TicketStatus::Closed,
        'category_id' => $category->id,
        'customer_id' => $customer->id,
    ]);

    $openSpec = new OpenTicketSpecification;
    $closedSpec = new StatusTicketSpecification(TicketStatus::Closed);

    // In-memory verification
    expect($openSpec->isSatisfiedBy($openTicket))->toBeTrue()
        ->and($openSpec->isSatisfiedBy($closedTicket))->toBeFalse()
        ->and($closedSpec->isSatisfiedBy($closedTicket))->toBeTrue()
        ->and($closedSpec->isSatisfiedBy($openTicket))->toBeFalse();

    // Query builder verification
    $openResults = Ticket::query()->matching($openSpec)->get();
    expect($openResults->pluck('id')->all())->toContain($openTicket->id)
        ->and($openResults->pluck('id')->all())->not->toContain($closedTicket->id);

    $closedResults = Ticket::query()->matching($closedSpec)->get();
    expect($closedResults->pluck('id')->all())->toContain($closedTicket->id)
        ->and($closedResults->pluck('id')->all())->not->toContain($openTicket->id);
});

test('priority specifications correctly identify urgent and prioritized tickets', function (): void {
    $urgentTicket = Ticket::factory()->create(['priority' => TicketPriority::Urgent]);
    $lowTicket = Ticket::factory()->create(['priority' => TicketPriority::Low]);

    $urgentSpec = new UrgentTicketSpecification;
    $lowSpec = new PriorityTicketSpecification(TicketPriority::Low);

    expect($urgentSpec->isSatisfiedBy($urgentTicket))->toBeTrue()
        ->and($urgentSpec->isSatisfiedBy($lowTicket))->toBeFalse()
        ->and($lowSpec->isSatisfiedBy($lowTicket))->toBeTrue();

    $urgentFromDb = Ticket::query()->matching($urgentSpec)->pluck('id')->all();
    expect($urgentFromDb)->toContain($urgentTicket->id)
        ->and($urgentFromDb)->not->toContain($lowTicket->id);
});

test('assignment and customer specifications filter tickets by agent and customer', function (): void {
    $agentA = User::factory()->create(['role' => Role::Agent]);
    $agentB = User::factory()->create(['role' => Role::Agent]);
    $customer = User::factory()->create(['role' => Role::Customer]);

    $assignedTicket = Ticket::factory()->create(['assigned_to' => $agentA->id, 'customer_id' => $customer->id]);
    $unassignedTicket = Ticket::factory()->create(['assigned_to' => null, 'customer_id' => $customer->id]);

    $assignedToAgentASpec = new AssignedToAgentSpecification($agentA);
    $unassignedSpec = new UnassignedTicketSpecification;
    $customerSpec = new CustomerTicketsSpecification($customer);

    expect($assignedToAgentASpec->isSatisfiedBy($assignedTicket))->toBeTrue()
        ->and($assignedToAgentASpec->isSatisfiedBy($unassignedTicket))->toBeFalse()
        ->and($unassignedSpec->isSatisfiedBy($unassignedTicket))->toBeTrue()
        ->and($unassignedSpec->isSatisfiedBy($assignedTicket))->toBeFalse()
        ->and($customerSpec->isSatisfiedBy($assignedTicket))->toBeTrue();

    $unassignedFromDb = Ticket::query()->matching($unassignedSpec)->pluck('id')->all();
    expect($unassignedFromDb)->toContain($unassignedTicket->id)
        ->and($unassignedFromDb)->not->toContain($assignedTicket->id);
});

test('composite specifications compose with and, or, not operators', function (): void {
    $agent = User::factory()->create(['role' => Role::Agent]);

    $targetTicket = Ticket::factory()->create([
        'status' => TicketStatus::Open,
        'priority' => TicketPriority::Urgent,
        'assigned_to' => null,
    ]);

    $otherTicket = Ticket::factory()->create([
        'status' => TicketStatus::Closed,
        'priority' => TicketPriority::Urgent,
        'assigned_to' => $agent->id,
    ]);

    // Unassigned AND Urgent AND Open
    $compositeSpec = (new UnassignedTicketSpecification)
        ->and(new UrgentTicketSpecification)
        ->and(new OpenTicketSpecification);

    expect($compositeSpec->isSatisfiedBy($targetTicket))->toBeTrue()
        ->and($compositeSpec->isSatisfiedBy($otherTicket))->toBeFalse();

    $matched = Ticket::query()->matching($compositeSpec)->pluck('id')->all();
    expect($matched)->toContain($targetTicket->id)
        ->and($matched)->not->toContain($otherTicket->id);

    // NOT Open
    $notOpenSpec = (new OpenTicketSpecification)->not();
    expect($notOpenSpec->isSatisfiedBy($otherTicket))->toBeTrue()
        ->and($notOpenSpec->isSatisfiedBy($targetTicket))->toBeFalse();

    // OR combination
    $orSpec = (new StatusTicketSpecification(TicketStatus::Closed))
        ->or(new PriorityTicketSpecification(TicketPriority::Urgent));

    expect($orSpec->isSatisfiedBy($targetTicket))->toBeTrue()
        ->and($orSpec->isSatisfiedBy($otherTicket))->toBeTrue();
});

test('overdue ticket specification identifies open tickets exceeding aging threshold', function (): void {
    $now = Carbon::parse('2026-09-29 12:00:00');
    Carbon::setTestNow($now);

    $oldOpenTicket = Ticket::factory()->create([
        'status' => TicketStatus::Open,
        'created_at' => $now->copy()->subHours(48),
    ]);

    $recentOpenTicket = Ticket::factory()->create([
        'status' => TicketStatus::Open,
        'created_at' => $now->copy()->subHours(2),
    ]);

    $oldClosedTicket = Ticket::factory()->create([
        'status' => TicketStatus::Closed,
        'created_at' => $now->copy()->subHours(48),
    ]);

    $overdueSpec = new OverdueTicketSpecification($now->copy()->subHours(24));

    expect($overdueSpec->isSatisfiedBy($oldOpenTicket))->toBeTrue()
        ->and($overdueSpec->isSatisfiedBy($recentOpenTicket))->toBeFalse()
        ->and($overdueSpec->isSatisfiedBy($oldClosedTicket))->toBeFalse();

    $overdueFromDb = Ticket::query()->matching($overdueSpec)->pluck('id')->all();
    expect($overdueFromDb)->toContain($oldOpenTicket->id)
        ->and($overdueFromDb)->not->toContain($recentOpenTicket->id)
        ->and($overdueFromDb)->not->toContain($oldClosedTicket->id);
});

<?php

declare(strict_types=1);

use App\Domain\Identity\Models\User;
use App\Domain\Ticket\Enums\TicketStatus;
use App\Domain\Ticket\Exceptions\InvalidTicketStateTransitionException;
use App\Domain\Ticket\Models\Category;
use App\Domain\Ticket\Models\Ticket;
use App\Domain\Ticket\States\ClosedTicketState;
use App\Domain\Ticket\States\InProgressTicketState;
use App\Domain\Ticket\States\OpenTicketState;
use App\Domain\Ticket\States\ResolvedTicketState;
use App\Domain\Ticket\States\WaitingForCustomerTicketState;

beforeEach(function (): void {
    $this->category = Category::factory()->create();
    $this->customer = User::factory()->create();
});

test('ticket resolves to correct initial state class', function (): void {
    $ticket = Ticket::factory()->create([
        'status' => TicketStatus::Open,
        'category_id' => $this->category->id,
        'customer_id' => $this->customer->id,
    ]);

    expect($ticket->state())->toBeInstanceOf(OpenTicketState::class)
        ->and($ticket->state()->status())->toBe(TicketStatus::Open);
});

test('ticket can transition through normal lifecycle from open to closed', function (): void {
    $ticket = Ticket::factory()->create([
        'status' => TicketStatus::Open,
        'category_id' => $this->category->id,
        'customer_id' => $this->customer->id,
    ]);

    // Open -> In Progress
    $ticket->state()->startProgress();
    $ticket->refresh();
    expect($ticket->status)->toBe(TicketStatus::InProgess)
        ->and($ticket->state())->toBeInstanceOf(InProgressTicketState::class);

    // In Progress -> Waiting For Customer
    $ticket->state()->waitForCustomer();
    $ticket->refresh();
    expect($ticket->status)->toBe(TicketStatus::WaitingForCustomer)
        ->and($ticket->state())->toBeInstanceOf(WaitingForCustomerTicketState::class);

    // Waiting For Customer -> In Progress
    $ticket->state()->startProgress();
    $ticket->refresh();
    expect($ticket->status)->toBe(TicketStatus::InProgess);

    // In Progress -> Resolved
    $ticket->state()->resolve();
    $ticket->refresh();
    expect($ticket->status)->toBe(TicketStatus::Resolved)
        ->and($ticket->state())->toBeInstanceOf(ResolvedTicketState::class);

    // Resolved -> Closed
    $ticket->state()->close();
    $ticket->refresh();
    expect($ticket->status)->toBe(TicketStatus::Closed)
        ->and($ticket->state())->toBeInstanceOf(ClosedTicketState::class);
});

test('ticket can be reopened from closed state', function (): void {
    $ticket = Ticket::factory()->create([
        'status' => TicketStatus::Closed,
        'category_id' => $this->category->id,
        'customer_id' => $this->customer->id,
    ]);

    expect($ticket->state())->toBeInstanceOf(ClosedTicketState::class);

    $ticket->state()->reopen();
    $ticket->refresh();

    expect($ticket->status)->toBe(TicketStatus::Open)
        ->and($ticket->state())->toBeInstanceOf(OpenTicketState::class);
});

test('ticket throws exception on invalid state transition', function (): void {
    $ticket = Ticket::factory()->create([
        'status' => TicketStatus::Open,
        'category_id' => $this->category->id,
        'customer_id' => $this->customer->id,
    ]);

    expect(fn () => $ticket->transitionTo(TicketStatus::WaitingForCustomer))
        ->toThrow(InvalidTicketStateTransitionException::class);
});

test('ticket can transition directly using model helper', function (): void {
    $ticket = Ticket::factory()->create([
        'status' => TicketStatus::Open,
        'category_id' => $this->category->id,
        'customer_id' => $this->customer->id,
    ]);

    $ticket->transitionTo(TicketStatus::InProgess);
    $ticket->refresh();

    expect($ticket->status)->toBe(TicketStatus::InProgess);
});

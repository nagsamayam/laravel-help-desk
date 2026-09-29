<?php

declare(strict_types=1);

use App\Domain\Identity\Enums\Role;
use App\Domain\Identity\Models\User;
use App\Domain\Ticket\Actions\AddTicketMessageAction;
use App\Domain\Ticket\Actions\AssignTicketAction;
use App\Domain\Ticket\Actions\CloseTicketAction;
use App\Domain\Ticket\Actions\CreateTicketAction;
use App\Domain\Ticket\Actions\DeleteTicketAction;
use App\Domain\Ticket\Actions\ReopenTicketAction;
use App\Domain\Ticket\Actions\ResolveTicketAction;
use App\Domain\Ticket\Actions\UpdateTicketAction;
use App\Domain\Ticket\DTOs\CreateTicketData;
use App\Domain\Ticket\DTOs\UpdateTicketData;
use App\Domain\Ticket\Enums\TicketPriority;
use App\Domain\Ticket\Enums\TicketStatus;
use App\Domain\Ticket\Events\TicketAssigned;
use App\Domain\Ticket\Events\TicketCreated;
use App\Domain\Ticket\Events\TicketMessageAdded;
use App\Domain\Ticket\Events\TicketStatusChanged;
use App\Domain\Ticket\Models\Category;
use App\Domain\Ticket\Models\Ticket;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;

uses(RefreshDatabase::class);

test('create ticket action creates ticket and dispatches event', function (): void {
    Event::fake([TicketCreated::class]);

    $customer = User::factory()->create(['role' => Role::Customer]);
    $category = Category::factory()->create();

    $dto = new CreateTicketData(
        customer_id: $customer->id,
        category_id: $category->id,
        subject: 'Database connection issue',
        description: 'Unable to reach replica server.',
        priority: TicketPriority::High,
    );

    $action = new CreateTicketAction;
    $ticket = $action->execute($dto);

    expect($ticket->id)->not->toBeNull()
        ->and($ticket->subject)->toBe('Database connection issue')
        ->and($ticket->status)->toBe(TicketStatus::Open)
        ->and($ticket->priority)->toBe(TicketPriority::High);

    Event::assertDispatched(TicketCreated::class, fn (TicketCreated $event) => $event->ticket->id === $ticket->id);
});

test('resolve, close, and reopen actions execute status transitions and dispatch events', function (): void {
    Event::fake([TicketStatusChanged::class]);

    $ticket = Ticket::factory()->create(['status' => TicketStatus::InProgess]);

    $resolveAction = new ResolveTicketAction;
    $resolved = $resolveAction->execute($ticket);
    expect($resolved->status)->toBe(TicketStatus::Resolved);

    Event::assertDispatched(TicketStatusChanged::class, function (TicketStatusChanged $event) use ($ticket): bool {
        return $event->ticket->id === $ticket->id
            && $event->previousStatus === TicketStatus::InProgess
            && $event->newStatus === TicketStatus::Resolved;
    });

    $closeAction = new CloseTicketAction;
    $closed = $closeAction->execute($resolved);
    expect($closed->status)->toBe(TicketStatus::Closed);

    Event::assertDispatched(TicketStatusChanged::class, function (TicketStatusChanged $event) use ($ticket): bool {
        return $event->ticket->id === $ticket->id
            && $event->previousStatus === TicketStatus::Resolved
            && $event->newStatus === TicketStatus::Closed;
    });

    $reopenAction = new ReopenTicketAction;
    $reopened = $reopenAction->execute($closed);
    expect($reopened->status)->toBe(TicketStatus::Open);

    Event::assertDispatched(TicketStatusChanged::class, function (TicketStatusChanged $event) use ($ticket): bool {
        return $event->ticket->id === $ticket->id
            && $event->previousStatus === TicketStatus::Closed
            && $event->newStatus === TicketStatus::Open;
    });
});

test('assign ticket action updates assignee and dispatches assignment event', function (): void {
    Event::fake([TicketAssigned::class]);

    $agent = User::factory()->create(['role' => Role::Agent]);
    $ticket = Ticket::factory()->create(['assigned_to' => null]);

    $action = new AssignTicketAction;
    $assignedTicket = $action->execute($ticket, $agent);

    expect($assignedTicket->assigned_to)->toBe($agent->id);

    Event::assertDispatched(TicketAssigned::class, function (TicketAssigned $event) use ($ticket, $agent): bool {
        return $event->ticket->id === $ticket->id
            && $event->agent?->id === $agent->id
            && $event->previousAgentId === null;
    });
});

test('add ticket message action creates message and dispatches event', function (): void {
    Event::fake([TicketMessageAdded::class]);

    $ticket = Ticket::factory()->create();
    $user = User::factory()->create();

    $action = new AddTicketMessageAction;
    $message = $action->execute($ticket, $user, 'Here is the requested log file.', false);

    expect($message->id)->not->toBeNull()
        ->and($message->ticket_id)->toBe($ticket->id)
        ->and($message->user_id)->toBe($user->id)
        ->and($message->message)->toBe('Here is the requested log file.')
        ->and($message->is_internal)->toBeFalse();

    Event::assertDispatched(TicketMessageAdded::class, function (TicketMessageAdded $event) use ($ticket, $message): bool {
        return $event->ticket->id === $ticket->id && $event->message->id === $message->id;
    });
});

test('update and delete actions modify and remove tickets', function (): void {
    $ticket = Ticket::factory()->create(['subject' => 'Old Subject']);

    $updateAction = new UpdateTicketAction;
    $updated = $updateAction->execute($ticket, new UpdateTicketData(
        subject: 'Updated Subject',
        description: 'Updated Description',
        category_id: $ticket->category_id,
        priority: TicketPriority::Urgent,
    ));

    expect($updated->subject)->toBe('Updated Subject')
        ->and($updated->priority)->toBe(TicketPriority::Urgent);

    $deleteAction = new DeleteTicketAction;
    $deleteAction->execute($updated);

    expect(Ticket::query()->find($ticket->id))->toBeNull();
});

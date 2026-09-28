<?php

declare(strict_types=1);

use App\Actions\AddTicketMessageAction;
use App\Actions\AssignTicketAction;
use App\Actions\CreateTicketAction;
use App\Actions\ResolveTicketAction;
use App\DTOs\CreateTicketData;
use App\Enums\Role;
use App\Enums\TicketPriority;
use App\Enums\TicketStatus;
use App\Events\Tickets\TicketAssigned;
use App\Events\Tickets\TicketCreated;
use App\Events\Tickets\TicketMessageAdded;
use App\Events\Tickets\TicketStatusChanged;
use App\Listeners\Tickets\LogTicketActivityListener;
use App\Listeners\Tickets\SendTicketNotificationListener;
use App\Models\Category;
use App\Models\Ticket;
use App\Models\User;
use App\Services\Notifications\NotificationResult;
use App\Services\Notifications\NotificationSenderInterface;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Log;

uses(RefreshDatabase::class);

test('ticket observer observes model lifecycle events', function (): void {
    Log::spy();

    $customer = User::factory()->create(['role' => Role::Customer]);
    $agent = User::factory()->create(['role' => Role::Agent]);
    $category = Category::factory()->create();

    $ticket = Ticket::factory()->create([
        'customer_id' => $customer->id,
        'category_id' => $category->id,
        'status' => TicketStatus::Open,
        'assigned_to' => null,
    ]);

    Log::shouldHaveReceived('info')->with('Ticket model created', Mockery::subset([
        'ticket_id' => $ticket->id,
        'customer_id' => $customer->id,
    ]));

    $ticket->update(['status' => TicketStatus::InProgess, 'assigned_to' => $agent->id]);

    Log::shouldHaveReceived('info')->with('Ticket status changed via model update', Mockery::subset([
        'ticket_id' => $ticket->id,
        'from' => TicketStatus::Open->value,
        'to' => TicketStatus::InProgess->value,
    ]));

    Log::shouldHaveReceived('info')->with('Ticket assigned_to changed via model update', Mockery::subset([
        'ticket_id' => $ticket->id,
        'assigned_to' => $agent->id,
    ]));

    $ticket->delete();

    Log::shouldHaveReceived('info')->with('Ticket model deleted', Mockery::subset([
        'ticket_id' => $ticket->id,
    ]));
});

test('domain events dispatch properly across actions and listeners handle them', function (): void {
    Event::fake([
        TicketCreated::class,
        TicketStatusChanged::class,
        TicketAssigned::class,
        TicketMessageAdded::class,
    ]);

    $customer = User::factory()->create();
    $agent = User::factory()->create(['role' => Role::Agent]);
    $category = Category::factory()->create();

    $createAction = new CreateTicketAction;
    $ticket = $createAction->execute(new CreateTicketData(
        customer_id: $customer->id,
        category_id: $category->id,
        subject: 'Printer outage',
        description: 'Office 3rd floor printer is offline.',
        priority: TicketPriority::Medium,
    ));

    Event::assertDispatched(TicketCreated::class);

    $assignAction = new AssignTicketAction;
    $assignAction->execute($ticket, $agent);
    Event::assertDispatched(TicketAssigned::class);

    $messageAction = new AddTicketMessageAction;
    $messageAction->execute($ticket, $customer, 'Is there any update?');
    Event::assertDispatched(TicketMessageAdded::class);

    $ticket->status = TicketStatus::InProgess;
    $ticket->save();

    $resolveAction = new ResolveTicketAction;
    $resolveAction->execute($ticket);
    Event::assertDispatched(TicketStatusChanged::class);
});

test('send ticket notification listener interacts with notification sender on domain events', function (): void {
    $sender = Mockery::mock(NotificationSenderInterface::class);

    $customer = User::factory()->create(['email' => 'customer@test.com']);
    $agent = User::factory()->create(['email' => 'agent@test.com', 'role' => Role::Agent]);
    $ticket = Ticket::factory()->create([
        'customer_id' => $customer->id,
        'assigned_to' => $agent->id,
        'subject' => 'Payment issue',
    ]);

    $sender->shouldReceive('send')
        ->once()
        ->with(
            Mockery::on(fn (User $u) => $u->id === $customer->id),
            "Ticket #{$ticket->id} Created",
            Mockery::any(),
            Mockery::any()
        )
        ->andReturn(NotificationResult::success($customer->email));

    $sender->shouldReceive('send')
        ->once()
        ->with(
            Mockery::on(fn (User $u) => $u->id === $agent->id),
            "Ticket #{$ticket->id} Assigned to You",
            Mockery::any(),
            Mockery::any()
        )
        ->andReturn(NotificationResult::success($agent->email));

    $listener = new SendTicketNotificationListener($sender);

    $listener->handleTicketCreated(new TicketCreated($ticket));
    $listener->handleTicketAssigned(new TicketAssigned($ticket, $agent));
});

test('log activity listener logs entries for domain events', function (): void {
    Log::spy();

    $customer = User::factory()->create();
    $ticket = Ticket::factory()->create(['customer_id' => $customer->id]);

    $listener = new LogTicketActivityListener;
    $listener->handleTicketCreated(new TicketCreated($ticket));

    Log::shouldHaveReceived('info')->with('Activity: TicketCreated', Mockery::subset([
        'ticket_id' => $ticket->id,
        'customer_id' => $customer->id,
    ]));
});

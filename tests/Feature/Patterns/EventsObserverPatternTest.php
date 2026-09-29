<?php

declare(strict_types=1);

use App\Domain\Identity\Enums\Role;
use App\Domain\Identity\Models\User;
use App\Domain\Ticket\Actions\AddTicketMessageAction;
use App\Domain\Ticket\Actions\AssignTicketAction;
use App\Domain\Ticket\Actions\CreateTicketAction;
use App\Domain\Ticket\Actions\ResolveTicketAction;
use App\Domain\Ticket\DTOs\CreateTicketData;
use App\Domain\Ticket\Enums\TicketPriority;
use App\Domain\Ticket\Enums\TicketStatus;
use App\Domain\Ticket\Events\TicketAssigned;
use App\Domain\Ticket\Events\TicketCreated;
use App\Domain\Ticket\Events\TicketMessageAdded;
use App\Domain\Ticket\Events\TicketStatusChanged;
use App\Domain\Ticket\Listeners\LogTicketActivityListener;
use App\Domain\Ticket\Listeners\SendTicketNotificationListener;
use App\Domain\Ticket\Models\Category;
use App\Domain\Ticket\Models\Ticket;
use App\Infrastructure\Notifications\NotificationResult;
use App\Infrastructure\Notifications\NotificationSenderInterface;
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

test('ticket creation end-to-end automatically executes log and notification listeners via event dispatcher', function (): void {
    Log::spy();

    $sender = Mockery::mock(NotificationSenderInterface::class);
    app()->instance(NotificationSenderInterface::class, $sender);

    $customer = User::factory()->create(['email' => 'customer.auto@test.com']);
    $category = Category::factory()->create();

    $sender->shouldReceive('send')
        ->once()
        ->with(
            Mockery::on(fn (User $u) => $u->id === $customer->id),
            Mockery::pattern('/Ticket #\d+ Created/'),
            Mockery::pattern('/Your ticket .* has been received/'),
            Mockery::subset(['event' => 'TicketCreated'])
        )
        ->andReturn(NotificationResult::success($customer->email));

    $createAction = new CreateTicketAction;
    $ticket = $createAction->execute(new CreateTicketData(
        customer_id: $customer->id,
        category_id: $category->id,
        subject: 'Automatic notification check',
        description: 'Verifying end-to-end listener execution.',
        priority: TicketPriority::Medium,
    ));

    Log::shouldHaveReceived('info')->with('Activity: TicketCreated', Mockery::subset([
        'ticket_id' => $ticket->id,
        'subject' => 'Automatic notification check',
        'customer_id' => $customer->id,
    ]));
});

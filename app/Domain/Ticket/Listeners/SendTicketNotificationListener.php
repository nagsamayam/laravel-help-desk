<?php

declare(strict_types=1);

namespace App\Domain\Ticket\Listeners;

use App\Domain\Identity\Enums\Role;
use App\Domain\Identity\Models\User;
use App\Domain\Ticket\Events\TicketAssigned;
use App\Domain\Ticket\Events\TicketCreated;
use App\Domain\Ticket\Events\TicketMessageAdded;
use App\Domain\Ticket\Events\TicketStatusChanged;
use App\Infrastructure\Notifications\NotificationSenderInterface;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\Middleware\RateLimited;
use Illuminate\Queue\Middleware\ThrottlesExceptions;

final class SendTicketNotificationListener implements ShouldQueue
{
    use InteractsWithQueue;

    public string $queue = 'notifications';

    public int $tries = 3;

    /**
     * @var array<int, int>
     */
    public array $backoff = [10, 30, 60];

    public int $timeout = 60;

    public bool $afterCommit = true;

    public function __construct(
        private readonly ?NotificationSenderInterface $notificationSender = null,
    ) {}

    /**
     * Get the middleware the job should pass through.
     *
     * @return array<int, object>
     */
    public function middleware(): array
    {
        return [
            new RateLimited('notifications'),
            new ThrottlesExceptions(maxAttempts: 5, decaySeconds: 300),
        ];
    }

    private function getSender(): ?NotificationSenderInterface
    {
        return $this->notificationSender ?? (app()->bound(NotificationSenderInterface::class) ? app(NotificationSenderInterface::class) : null);
    }

    public function handleTicketCreated(TicketCreated $event): void
    {
        $sender = $this->getSender();
        if ($sender === null) {
            return;
        }

        $event->ticket->loadMissing(['customer', 'assignee']);
        $notifiedUserIds = [];

        // 1. Notify Customer
        $customer = $event->ticket->customer;
        if ($customer !== null) {
            $sender->send(
                recipient: $customer,
                title: "Ticket #{$event->ticket->id} Created",
                content: "Your ticket '{$event->ticket->subject}' has been received.",
                context: ['ticket' => $event->ticket, 'ticket_id' => $event->ticket->id, 'event' => 'TicketCreated']
            );
            $notifiedUserIds[] = $customer->id;
        }

        // 2. If assigned on creation, notify Assigned Agent
        $assignee = $event->ticket->assignee;
        if ($assignee !== null && ! in_array($assignee->id, $notifiedUserIds, true)) {
            $sender->send(
                recipient: $assignee,
                title: "Ticket #{$event->ticket->id} Assigned to You",
                content: "You have been assigned to '{$event->ticket->subject}'.",
                context: ['ticket' => $event->ticket, 'ticket_id' => $event->ticket->id, 'assigned_to' => $assignee->id]
            );
            $notifiedUserIds[] = $assignee->id;
        }

        // 3. Notify Admins if unassigned so support team can triage
        if ($assignee === null) {
            $admins = User::query()->where('role', Role::Admin)->get();
            foreach ($admins as $admin) {
                if (! in_array($admin->id, $notifiedUserIds, true)) {
                    $sender->send(
                        recipient: $admin,
                        title: "New Ticket #{$event->ticket->id}: {$event->ticket->subject}",
                        content: "A new support ticket has been submitted by {$customer?->first_name} {$customer?->last_name}.",
                        context: ['ticket' => $event->ticket, 'ticket_id' => $event->ticket->id, 'event' => 'NewTicketTriage']
                    );
                    $notifiedUserIds[] = $admin->id;
                }
            }
        }
    }

    public function handleTicketStatusChanged(TicketStatusChanged $event): void
    {
        $sender = $this->getSender();
        if ($sender === null) {
            return;
        }

        $event->ticket->loadMissing(['customer', 'assignee']);
        $notifiedUserIds = [];

        // 1. Notify Customer
        $customer = $event->ticket->customer;
        if ($customer !== null) {
            $sender->send(
                recipient: $customer,
                title: "Ticket #{$event->ticket->id} Status Updated",
                content: "Status changed to {$event->newStatus->value}.",
                context: ['ticket' => $event->ticket, 'ticket_id' => $event->ticket->id, 'status' => $event->newStatus->value]
            );
            $notifiedUserIds[] = $customer->id;
        }

        // 2. Notify Assigned Agent
        $assignee = $event->ticket->assignee;
        if ($assignee !== null && ! in_array($assignee->id, $notifiedUserIds, true)) {
            $sender->send(
                recipient: $assignee,
                title: "Ticket #{$event->ticket->id} Status Changed",
                content: "Ticket '{$event->ticket->subject}' status changed to {$event->newStatus->value}.",
                context: ['ticket' => $event->ticket, 'ticket_id' => $event->ticket->id, 'status' => $event->newStatus->value]
            );
            $notifiedUserIds[] = $assignee->id;
        }
    }

    public function handleTicketAssigned(TicketAssigned $event): void
    {
        $sender = $this->getSender();
        if ($sender === null) {
            return;
        }

        $event->ticket->loadMissing('customer');
        $notifiedUserIds = [];

        // 1. Notify Assigned Agent
        if ($event->agent !== null) {
            $sender->send(
                recipient: $event->agent,
                title: "Ticket #{$event->ticket->id} Assigned to You",
                content: "You have been assigned to '{$event->ticket->subject}'.",
                context: ['ticket' => $event->ticket, 'ticket_id' => $event->ticket->id, 'assigned_to' => $event->agent->id]
            );
            $notifiedUserIds[] = $event->agent->id;
        }

        // 2. Notify Customer about the assigned agent
        $customer = $event->ticket->customer;
        if ($customer !== null && ! in_array($customer->id, $notifiedUserIds, true)) {
            $agentName = $event->agent ? "{$event->agent->first_name} {$event->agent->last_name}" : 'A support agent';
            $sender->send(
                recipient: $customer,
                title: "Agent Assigned to Ticket #{$event->ticket->id}",
                content: "{$agentName} has been assigned to your ticket '{$event->ticket->subject}'.",
                context: ['ticket' => $event->ticket, 'ticket_id' => $event->ticket->id, 'assigned_agent' => $agentName]
            );
            $notifiedUserIds[] = $customer->id;
        }
    }

    public function handleTicketMessageAdded(TicketMessageAdded $event): void
    {
        $sender = $this->getSender();
        if ($sender === null) {
            return;
        }

        $event->ticket->loadMissing(['customer', 'assignee']);
        $notifiedUserIds = [];

        // Handle Internal Note vs Public Reply
        if ($event->message->is_internal) {
            // Internal notes are staff-only: never notify the customer!
            $assignee = $event->ticket->assignee;
            if ($assignee !== null && $assignee->id !== $event->message->user_id) {
                $sender->send(
                    recipient: $assignee,
                    title: "Internal Note on Ticket #{$event->ticket->id}",
                    content: $event->message->message,
                    context: ['ticket' => $event->ticket, 'ticket_id' => $event->ticket->id, 'message_id' => $event->message->id, 'internal' => true]
                );
                $notifiedUserIds[] = $assignee->id;
            }

            // Also notify admins if sender is not an admin
            $admins = User::query()->where('role', Role::Admin)->get();
            foreach ($admins as $admin) {
                if ($admin->id !== $event->message->user_id && ! in_array($admin->id, $notifiedUserIds, true)) {
                    $sender->send(
                        recipient: $admin,
                        title: "Internal Note on Ticket #{$event->ticket->id}",
                        content: $event->message->message,
                        context: ['ticket' => $event->ticket, 'ticket_id' => $event->ticket->id, 'message_id' => $event->message->id, 'internal' => true]
                    );
                    $notifiedUserIds[] = $admin->id;
                }
            }

            return;
        }

        // Public message: determine recipient based on sender
        if ($event->message->user_id === $event->ticket->customer_id) {
            // Message from customer -> notify assignee or admins
            if ($event->ticket->assignee !== null) {
                $sender->send(
                    recipient: $event->ticket->assignee,
                    title: "New reply on Ticket #{$event->ticket->id}",
                    content: $event->message->message,
                    context: ['ticket' => $event->ticket, 'ticket_id' => $event->ticket->id, 'message_id' => $event->message->id]
                );
            } else {
                $admins = User::query()->where('role', Role::Admin)->get();
                foreach ($admins as $admin) {
                    if (! in_array($admin->id, $notifiedUserIds, true)) {
                        $sender->send(
                            recipient: $admin,
                            title: "New customer reply on Ticket #{$event->ticket->id}",
                            content: $event->message->message,
                            context: ['ticket' => $event->ticket, 'ticket_id' => $event->ticket->id, 'message_id' => $event->message->id]
                        );
                        $notifiedUserIds[] = $admin->id;
                    }
                }
            }
        } else {
            // Message from agent or admin -> notify customer
            if ($event->ticket->customer !== null) {
                $sender->send(
                    recipient: $event->ticket->customer,
                    title: "New reply on Ticket #{$event->ticket->id}",
                    content: $event->message->message,
                    context: ['ticket' => $event->ticket, 'ticket_id' => $event->ticket->id, 'message_id' => $event->message->id]
                );
            }
        }
    }

    /**
     * @param  TicketCreated|TicketStatusChanged|TicketAssigned|TicketMessageAdded  $event
     */
    public function handle(object $event): void
    {
        match (true) {
            $event instanceof TicketCreated => $this->handleTicketCreated($event),
            $event instanceof TicketStatusChanged => $this->handleTicketStatusChanged($event),
            $event instanceof TicketAssigned => $this->handleTicketAssigned($event),
            $event instanceof TicketMessageAdded => $this->handleTicketMessageAdded($event),
            default => null,
        };
    }
}

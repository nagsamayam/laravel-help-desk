<?php

declare(strict_types=1);

namespace App\Domain\Ticket\Listeners;

use App\Domain\Ticket\Events\TicketAssigned;
use App\Domain\Ticket\Events\TicketCreated;
use App\Domain\Ticket\Events\TicketMessageAdded;
use App\Domain\Ticket\Events\TicketStatusChanged;
use App\Infrastructure\Notifications\NotificationSenderInterface;

final class SendTicketNotificationListener
{
    public function __construct(
        private readonly ?NotificationSenderInterface $notificationSender = null,
    ) {}

    public function handleTicketCreated(TicketCreated $event): void
    {
        if ($this->notificationSender === null) {
            return;
        }

        $event->ticket->loadMissing('customer');
        $customer = $event->ticket->customer;
        if ($customer !== null) {
            $this->notificationSender->send(
                recipient: $customer,
                title: "Ticket #{$event->ticket->id} Created",
                content: "Your ticket '{$event->ticket->subject}' has been received.",
                context: ['ticket_id' => $event->ticket->id, 'event' => 'TicketCreated']
            );
        }
    }

    public function handleTicketStatusChanged(TicketStatusChanged $event): void
    {
        if ($this->notificationSender === null) {
            return;
        }

        $event->ticket->loadMissing('customer');
        $customer = $event->ticket->customer;
        if ($customer !== null) {
            $this->notificationSender->send(
                recipient: $customer,
                title: "Ticket #{$event->ticket->id} Status Updated",
                content: "Status changed to {$event->newStatus->value}.",
                context: ['ticket_id' => $event->ticket->id, 'status' => $event->newStatus->value]
            );
        }
    }

    public function handleTicketAssigned(TicketAssigned $event): void
    {
        if ($this->notificationSender === null || $event->agent === null) {
            return;
        }

        $this->notificationSender->send(
            recipient: $event->agent,
            title: "Ticket #{$event->ticket->id} Assigned to You",
            content: "You have been assigned to '{$event->ticket->subject}'.",
            context: ['ticket_id' => $event->ticket->id, 'assigned_to' => $event->agent->id]
        );
    }

    public function handleTicketMessageAdded(TicketMessageAdded $event): void
    {
        if ($this->notificationSender === null) {
            return;
        }

        $event->ticket->loadMissing(['customer', 'assignee']);

        $recipient = $event->message->user_id === $event->ticket->customer_id
            ? $event->ticket->assignee
            : $event->ticket->customer;

        if ($recipient !== null) {
            $this->notificationSender->send(
                recipient: $recipient,
                title: "New reply on Ticket #{$event->ticket->id}",
                content: $event->message->message,
                context: ['ticket_id' => $event->ticket->id, 'message_id' => $event->message->id]
            );
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

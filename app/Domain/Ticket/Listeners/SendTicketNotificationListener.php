<?php

declare(strict_types=1);

namespace App\Domain\Ticket\Listeners;

use App\Domain\Ticket\Events\TicketAssigned;
use App\Domain\Ticket\Events\TicketCreated;
use App\Domain\Ticket\Events\TicketMessageAdded;
use App\Domain\Ticket\Events\TicketStatusChanged;
use App\Infrastructure\Notifications\NotificationSenderInterface;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;

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

        $event->ticket->loadMissing('customer');
        $customer = $event->ticket->customer;
        if ($customer !== null) {
            $sender->send(
                recipient: $customer,
                title: "Ticket #{$event->ticket->id} Created",
                content: "Your ticket '{$event->ticket->subject}' has been received.",
                context: ['ticket_id' => $event->ticket->id, 'event' => 'TicketCreated']
            );
        }
    }

    public function handleTicketStatusChanged(TicketStatusChanged $event): void
    {
        $sender = $this->getSender();
        if ($sender === null) {
            return;
        }

        $event->ticket->loadMissing('customer');
        $customer = $event->ticket->customer;
        if ($customer !== null) {
            $sender->send(
                recipient: $customer,
                title: "Ticket #{$event->ticket->id} Status Updated",
                content: "Status changed to {$event->newStatus->value}.",
                context: ['ticket_id' => $event->ticket->id, 'status' => $event->newStatus->value]
            );
        }
    }

    public function handleTicketAssigned(TicketAssigned $event): void
    {
        $sender = $this->getSender();
        if ($sender === null || $event->agent === null) {
            return;
        }

        $sender->send(
            recipient: $event->agent,
            title: "Ticket #{$event->ticket->id} Assigned to You",
            content: "You have been assigned to '{$event->ticket->subject}'.",
            context: ['ticket_id' => $event->ticket->id, 'assigned_to' => $event->agent->id]
        );
    }

    public function handleTicketMessageAdded(TicketMessageAdded $event): void
    {
        $sender = $this->getSender();
        if ($sender === null) {
            return;
        }

        $event->ticket->loadMissing(['customer', 'assignee']);

        $recipient = $event->message->user_id === $event->ticket->customer_id
            ? $event->ticket->assignee
            : $event->ticket->customer;

        if ($recipient !== null) {
            $sender->send(
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

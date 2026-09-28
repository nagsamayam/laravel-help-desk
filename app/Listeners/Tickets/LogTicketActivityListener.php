<?php

declare(strict_types=1);

namespace App\Listeners\Tickets;

use App\Events\Tickets\TicketAssigned;
use App\Events\Tickets\TicketCreated;
use App\Events\Tickets\TicketMessageAdded;
use App\Events\Tickets\TicketStatusChanged;
use Illuminate\Support\Facades\Log;

final class LogTicketActivityListener
{
    public function handleTicketCreated(TicketCreated $event): void
    {
        Log::info('Activity: TicketCreated', [
            'ticket_id' => $event->ticket->id,
            'subject' => $event->ticket->subject,
            'customer_id' => $event->ticket->customer_id,
        ]);
    }

    public function handleTicketStatusChanged(TicketStatusChanged $event): void
    {
        Log::info('Activity: TicketStatusChanged', [
            'ticket_id' => $event->ticket->id,
            'from' => $event->previousStatus->value,
            'to' => $event->newStatus->value,
        ]);
    }

    public function handleTicketAssigned(TicketAssigned $event): void
    {
        Log::info('Activity: TicketAssigned', [
            'ticket_id' => $event->ticket->id,
            'agent_id' => $event->agent?->id,
            'previous_agent_id' => $event->previousAgentId,
        ]);
    }

    public function handleTicketMessageAdded(TicketMessageAdded $event): void
    {
        Log::info('Activity: TicketMessageAdded', [
            'ticket_id' => $event->ticket->id,
            'message_id' => $event->message->id,
            'user_id' => $event->message->user_id,
        ]);
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

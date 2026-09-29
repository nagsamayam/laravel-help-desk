<?php

declare(strict_types=1);

namespace App\Domain\Ticket\Routing\Rules;

use App\Domain\Ticket\Models\Ticket;

abstract class TicketRoutingRule
{
    protected ?TicketRoutingRule $next = null;

    public function setNext(TicketRoutingRule $next): TicketRoutingRule
    {
        $this->next = $next;

        return $next;
    }

    public function handle(Ticket $ticket): TicketRoutingDecision
    {
        if ($this->shouldHandle($ticket)) {
            return $this->process($ticket);
        }

        if ($this->next !== null) {
            return $this->next->handle($ticket);
        }

        return $this->fallback($ticket);
    }

    abstract protected function shouldHandle(Ticket $ticket): bool;

    abstract protected function process(Ticket $ticket): TicketRoutingDecision;

    protected function fallback(Ticket $ticket): TicketRoutingDecision
    {
        return new TicketRoutingDecision(
            ticket: $ticket,
            priority: $ticket->priority,
            assignedTo: $ticket->assigned_to,
            categoryId: $ticket->category_id,
            matchedRule: null,
            reason: 'No routing rules matched.',
            tags: [],
        );
    }
}

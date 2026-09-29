<?php

declare(strict_types=1);

namespace App\Domain\Ticket\Routing\Rules;

use App\Domain\Ticket\Enums\TicketPriority;
use App\Domain\Ticket\Models\Ticket;

final class DefaultRoutingRule extends TicketRoutingRule
{
    public function __construct(
        private readonly TicketPriority $defaultPriority = TicketPriority::Low,
    ) {}

    protected function shouldHandle(Ticket $ticket): bool
    {
        return true;
    }

    protected function process(Ticket $ticket): TicketRoutingDecision
    {
        return new TicketRoutingDecision(
            ticket: $ticket,
            priority: $ticket->priority ?? $this->defaultPriority,
            assignedTo: $ticket->assigned_to,
            categoryId: $ticket->category_id,
            matchedRule: 'Default Rule',
            reason: 'Standard fallback routing applied.',
            tags: ['default', 'standard-queue'],
        );
    }
}

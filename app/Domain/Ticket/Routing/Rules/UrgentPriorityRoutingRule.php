<?php

declare(strict_types=1);

namespace App\Domain\Ticket\Routing\Rules;

use App\Domain\Ticket\Enums\TicketPriority;
use App\Domain\Ticket\Models\Ticket;
use Illuminate\Support\Str;

final class UrgentPriorityRoutingRule extends TicketRoutingRule
{
    /**
     * @param  array<int, string>  $urgentKeywords
     */
    public function __construct(
        private readonly array $urgentKeywords = [
            'urgent',
            'critical',
            'emergency',
            'outage',
            'downtime',
            'system down',
            'data loss',
            'crash',
            'production down',
        ],
    ) {}

    protected function shouldHandle(Ticket $ticket): bool
    {
        if ($ticket->priority === TicketPriority::Urgent) {
            return true;
        }

        $content = Str::lower($ticket->subject.' '.$ticket->description);

        foreach ($this->urgentKeywords as $keyword) {
            if (Str::contains($content, Str::lower($keyword))) {
                return true;
            }
        }

        return false;
    }

    protected function process(Ticket $ticket): TicketRoutingDecision
    {
        return new TicketRoutingDecision(
            ticket: $ticket,
            priority: TicketPriority::Urgent,
            assignedTo: $ticket->assigned_to,
            categoryId: $ticket->category_id,
            matchedRule: 'Urgent Rule',
            reason: 'Critical indicators or urgent keywords detected in ticket.',
            tags: ['urgent', 'critical'],
        );
    }
}

<?php

declare(strict_types=1);

namespace App\Routing\Rules;

use App\Enums\TicketPriority;
use App\Models\Ticket;

final class TicketRoutingDecision
{
    /**
     * @param  array<int, string>  $tags
     */
    public function __construct(
        public readonly Ticket $ticket,
        public readonly TicketPriority $priority,
        public readonly ?int $assignedTo = null,
        public readonly ?int $categoryId = null,
        public readonly ?string $matchedRule = null,
        public readonly ?string $reason = null,
        public readonly array $tags = [],
    ) {}

    public function apply(): Ticket
    {
        $this->ticket->priority = $this->priority;

        if ($this->assignedTo !== null) {
            $this->ticket->assigned_to = $this->assignedTo;
        }

        if ($this->categoryId !== null) {
            $this->ticket->category_id = $this->categoryId;
        }

        $this->ticket->save();

        return $this->ticket;
    }
}

<?php

declare(strict_types=1);

namespace App\Specifications\Ticket;

use App\Enums\TicketPriority;
use App\Models\Ticket;
use Illuminate\Database\Eloquent\Builder;

final class PriorityTicketSpecification extends TicketSpecification
{
    /** @var array<int, TicketPriority> */
    private readonly array $priorities;

    /**
     * @param  TicketPriority|array<int, TicketPriority>  $priority
     */
    public function __construct(TicketPriority|array $priority)
    {
        $this->priorities = is_array($priority) ? array_values($priority) : [$priority];
    }

    public function isSatisfiedBy(Ticket $ticket): bool
    {
        return in_array($ticket->priority, $this->priorities, true);
    }

    /**
     * @param  Builder<Ticket>  $query
     * @return Builder<Ticket>
     */
    public function apply(Builder $query): Builder
    {
        return $query->whereIn('priority', $this->priorities);
    }
}

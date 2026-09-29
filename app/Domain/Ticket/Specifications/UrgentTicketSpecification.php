<?php

declare(strict_types=1);

namespace App\Domain\Ticket\Specifications;

use App\Domain\Ticket\Enums\TicketPriority;
use App\Domain\Ticket\Models\Ticket;
use Illuminate\Database\Eloquent\Builder;

final class UrgentTicketSpecification extends TicketSpecification
{
    /** @var array<int, TicketPriority> */
    private const URGENT_PRIORITIES = [
        TicketPriority::Urgent,
        TicketPriority::High,
    ];

    public function isSatisfiedBy(Ticket $ticket): bool
    {
        return in_array($ticket->priority, self::URGENT_PRIORITIES, true);
    }

    /**
     * @param  Builder<Ticket>  $query
     * @return Builder<Ticket>
     */
    public function apply(Builder $query): Builder
    {
        return $query->whereIn('priority', self::URGENT_PRIORITIES);
    }
}

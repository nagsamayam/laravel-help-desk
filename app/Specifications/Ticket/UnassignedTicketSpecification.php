<?php

declare(strict_types=1);

namespace App\Specifications\Ticket;

use App\Models\Ticket;
use Illuminate\Database\Eloquent\Builder;

final class UnassignedTicketSpecification extends TicketSpecification
{
    public function isSatisfiedBy(Ticket $ticket): bool
    {
        return $ticket->assigned_to === null;
    }

    /**
     * @param  Builder<Ticket>  $query
     * @return Builder<Ticket>
     */
    public function apply(Builder $query): Builder
    {
        return $query->whereNull('assigned_to');
    }
}

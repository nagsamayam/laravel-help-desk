<?php

declare(strict_types=1);

namespace App\Specifications\Ticket;

use App\Models\Ticket;
use Illuminate\Database\Eloquent\Builder;

abstract class TicketSpecification
{
    /**
     * Check if a ticket satisfies the specification in-memory.
     */
    abstract public function isSatisfiedBy(Ticket $ticket): bool;

    /**
     * Apply the specification to an Eloquent query builder.
     *
     * @param  Builder<Ticket>  $query
     * @return Builder<Ticket>
     */
    abstract public function apply(Builder $query): Builder;

    public function and(TicketSpecification $other): TicketSpecification
    {
        return new AndSpecification($this, $other);
    }

    public function or(TicketSpecification $other): TicketSpecification
    {
        return new OrSpecification($this, $other);
    }

    public function not(): TicketSpecification
    {
        return new NotSpecification($this);
    }
}

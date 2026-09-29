<?php

declare(strict_types=1);

namespace App\Domain\Ticket\Specifications;

use App\Domain\Ticket\Models\Ticket;
use Illuminate\Database\Eloquent\Builder;

final class AndSpecification extends TicketSpecification
{
    public function __construct(
        private readonly TicketSpecification $first,
        private readonly TicketSpecification $second,
    ) {}

    public function isSatisfiedBy(Ticket $ticket): bool
    {
        return $this->first->isSatisfiedBy($ticket) && $this->second->isSatisfiedBy($ticket);
    }

    /**
     * @param  Builder<Ticket>  $query
     * @return Builder<Ticket>
     */
    public function apply(Builder $query): Builder
    {
        return $query->where(function (Builder $builder): void {
            $this->first->apply($builder);
            $this->second->apply($builder);
        });
    }
}

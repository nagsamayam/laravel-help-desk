<?php

declare(strict_types=1);

namespace App\Specifications\Ticket;

use App\Models\Ticket;
use Illuminate\Database\Eloquent\Builder;

final class OrSpecification extends TicketSpecification
{
    public function __construct(
        private readonly TicketSpecification $first,
        private readonly TicketSpecification $second,
    ) {}

    public function isSatisfiedBy(Ticket $ticket): bool
    {
        return $this->first->isSatisfiedBy($ticket) || $this->second->isSatisfiedBy($ticket);
    }

    /**
     * @param  Builder<Ticket>  $query
     * @return Builder<Ticket>
     */
    public function apply(Builder $query): Builder
    {
        return $query->where(function (Builder $builder): void {
            $builder->where(fn (Builder $b) => $this->first->apply($b))
                ->orWhere(fn (Builder $b) => $this->second->apply($b));
        });
    }
}

<?php

declare(strict_types=1);

namespace App\Specifications\Ticket;

use App\Models\Ticket;
use Illuminate\Database\Eloquent\Builder;

final class NotSpecification extends TicketSpecification
{
    public function __construct(
        private readonly TicketSpecification $specification,
    ) {}

    public function isSatisfiedBy(Ticket $ticket): bool
    {
        return ! $this->specification->isSatisfiedBy($ticket);
    }

    /**
     * @param  Builder<Ticket>  $query
     * @return Builder<Ticket>
     */
    public function apply(Builder $query): Builder
    {
        return $query->whereNot(function (Builder $builder): void {
            $this->specification->apply($builder);
        });
    }
}

<?php

declare(strict_types=1);

namespace App\Domain\Ticket\Specifications;

use App\Domain\Identity\Models\User;
use App\Domain\Ticket\Models\Ticket;
use Illuminate\Database\Eloquent\Builder;

final class CustomerTicketsSpecification extends TicketSpecification
{
    private readonly int $customerId;

    public function __construct(User|int $customer)
    {
        $this->customerId = $customer instanceof User ? $customer->id : (int) $customer;
    }

    public function isSatisfiedBy(Ticket $ticket): bool
    {
        return $ticket->customer_id === $this->customerId;
    }

    /**
     * @param  Builder<Ticket>  $query
     * @return Builder<Ticket>
     */
    public function apply(Builder $query): Builder
    {
        return $query->where('customer_id', $this->customerId);
    }
}

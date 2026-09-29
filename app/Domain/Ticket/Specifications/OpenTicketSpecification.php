<?php

declare(strict_types=1);

namespace App\Domain\Ticket\Specifications;

use App\Domain\Ticket\Enums\TicketStatus;
use App\Domain\Ticket\Models\Ticket;
use Illuminate\Database\Eloquent\Builder;

final class OpenTicketSpecification extends TicketSpecification
{
    /** @var array<int, TicketStatus> */
    private const OPEN_STATUSES = [
        TicketStatus::Open,
        TicketStatus::InProgess,
        TicketStatus::WaitingForCustomer,
    ];

    public function isSatisfiedBy(Ticket $ticket): bool
    {
        return in_array($ticket->status, self::OPEN_STATUSES, true);
    }

    /**
     * @param  Builder<Ticket>  $query
     * @return Builder<Ticket>
     */
    public function apply(Builder $query): Builder
    {
        return $query->whereIn('status', self::OPEN_STATUSES);
    }
}

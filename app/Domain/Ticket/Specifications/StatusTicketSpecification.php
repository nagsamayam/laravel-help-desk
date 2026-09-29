<?php

declare(strict_types=1);

namespace App\Domain\Ticket\Specifications;

use App\Domain\Ticket\Enums\TicketStatus;
use App\Domain\Ticket\Models\Ticket;
use Illuminate\Database\Eloquent\Builder;

final class StatusTicketSpecification extends TicketSpecification
{
    /** @var array<int, TicketStatus> */
    private readonly array $statuses;

    /**
     * @param  TicketStatus|array<int, TicketStatus>  $status
     */
    public function __construct(TicketStatus|array $status)
    {
        $this->statuses = is_array($status) ? array_values($status) : [$status];
    }

    public function isSatisfiedBy(Ticket $ticket): bool
    {
        return in_array($ticket->status, $this->statuses, true);
    }

    /**
     * @param  Builder<Ticket>  $query
     * @return Builder<Ticket>
     */
    public function apply(Builder $query): Builder
    {
        return $query->whereIn('status', $this->statuses);
    }
}

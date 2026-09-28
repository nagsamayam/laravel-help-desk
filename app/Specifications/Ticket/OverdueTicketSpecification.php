<?php

declare(strict_types=1);

namespace App\Specifications\Ticket;

use App\Enums\TicketStatus;
use App\Models\Ticket;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;

final class OverdueTicketSpecification extends TicketSpecification
{
    private readonly CarbonInterface $olderThan;

    public function __construct(?CarbonInterface $olderThan = null)
    {
        $this->olderThan = $olderThan ?? Carbon::now()->subHours(24);
    }

    public function isSatisfiedBy(Ticket $ticket): bool
    {
        $isClosed = in_array($ticket->status, [TicketStatus::Closed, TicketStatus::Resolved], true);
        if ($isClosed) {
            return false;
        }

        $createdAt = $ticket->created_at instanceof CarbonInterface
            ? $ticket->created_at
            : Carbon::parse((string) $ticket->created_at);

        return $createdAt->lessThan($this->olderThan);
    }

    /**
     * @param  Builder<Ticket>  $query
     * @return Builder<Ticket>
     */
    public function apply(Builder $query): Builder
    {
        return $query->whereNotIn('status', [TicketStatus::Closed, TicketStatus::Resolved])
            ->where('created_at', '<', $this->olderThan);
    }
}

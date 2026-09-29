<?php

declare(strict_types=1);

namespace App\Domain\Ticket\States;

use App\Domain\Ticket\Enums\TicketStatus;

final class ResolvedTicketState extends TicketState
{
    public function status(): TicketStatus
    {
        return TicketStatus::Resolved;
    }

    /**
     * @return array<int, TicketStatus>
     */
    public function allowedTransitions(): array
    {
        return [
            TicketStatus::Closed,
            TicketStatus::InProgess,
            TicketStatus::Open,
        ];
    }
}

<?php

declare(strict_types=1);

namespace App\Domain\Ticket\States;

use App\Domain\Ticket\Enums\TicketStatus;

final class InProgressTicketState extends TicketState
{
    public function status(): TicketStatus
    {
        return TicketStatus::InProgess;
    }

    /**
     * @return array<int, TicketStatus>
     */
    public function allowedTransitions(): array
    {
        return [
            TicketStatus::WaitingForCustomer,
            TicketStatus::Resolved,
            TicketStatus::Closed,
            TicketStatus::Open,
        ];
    }
}

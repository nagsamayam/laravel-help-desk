<?php

declare(strict_types=1);

namespace App\States\Ticket;

use App\Enums\TicketStatus;

final class OpenTicketState extends TicketState
{
    public function status(): TicketStatus
    {
        return TicketStatus::Open;
    }

    /**
     * @return array<int, TicketStatus>
     */
    public function allowedTransitions(): array
    {
        return [
            TicketStatus::InProgess,
            TicketStatus::Closed,
        ];
    }
}

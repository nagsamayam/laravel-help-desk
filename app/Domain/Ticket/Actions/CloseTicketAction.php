<?php

declare(strict_types=1);

namespace App\Domain\Ticket\Actions;

use App\Domain\Ticket\Enums\TicketStatus;
use App\Domain\Ticket\Events\TicketStatusChanged;
use App\Domain\Ticket\Models\Ticket;

final class CloseTicketAction
{
    public function execute(Ticket $ticket): Ticket
    {
        $previousStatus = $ticket->status;
        $closedTicket = $ticket->state()->close();

        if ($previousStatus !== TicketStatus::Closed) {
            TicketStatusChanged::dispatch($closedTicket, $previousStatus, TicketStatus::Closed);
        }

        return $closedTicket;
    }
}

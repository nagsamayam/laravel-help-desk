<?php

declare(strict_types=1);

namespace App\Actions;

use App\Enums\TicketStatus;
use App\Events\Tickets\TicketStatusChanged;
use App\Models\Ticket;

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

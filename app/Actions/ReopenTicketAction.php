<?php

declare(strict_types=1);

namespace App\Actions;

use App\Enums\TicketStatus;
use App\Events\Tickets\TicketStatusChanged;
use App\Models\Ticket;

final class ReopenTicketAction
{
    public function execute(Ticket $ticket): Ticket
    {
        $previousStatus = $ticket->status;
        $reopenedTicket = $ticket->state()->reopen();

        if ($previousStatus !== TicketStatus::Open) {
            TicketStatusChanged::dispatch($reopenedTicket, $previousStatus, TicketStatus::Open);
        }

        return $reopenedTicket;
    }
}

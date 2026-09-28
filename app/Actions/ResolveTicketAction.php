<?php

declare(strict_types=1);

namespace App\Actions;

use App\Enums\TicketStatus;
use App\Events\Tickets\TicketStatusChanged;
use App\Models\Ticket;

final class ResolveTicketAction
{
    public function execute(Ticket $ticket): Ticket
    {
        $previousStatus = $ticket->status;
        $resolvedTicket = $ticket->state()->resolve();

        if ($previousStatus !== TicketStatus::Resolved) {
            TicketStatusChanged::dispatch($resolvedTicket, $previousStatus, TicketStatus::Resolved);
        }

        return $resolvedTicket;
    }
}

<?php

declare(strict_types=1);

namespace App\Domain\Ticket\Actions;

use App\Domain\Ticket\Enums\TicketStatus;
use App\Domain\Ticket\Events\TicketStatusChanged;
use App\Domain\Ticket\Models\Ticket;

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

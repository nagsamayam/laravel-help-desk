<?php

declare(strict_types=1);

namespace App\Domain\Ticket\SLAEvaluator\Evaluators;

use App\Domain\Ticket\Models\Ticket;

class UrgentTicketDecorator extends TicketSlaDecorator
{
    public function calculateResolutionWindow(Ticket $ticket): int
    {
        // Fetch the time from the previous layer
        $hours = parent::calculateResolutionWindow($ticket);

        // If marked urgent, cut the resolution window in half
        if ($ticket->is_urgent) {
            return (int) ($hours / 2);
        }

        return $hours;
    }
}

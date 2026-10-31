<?php

declare(strict_types=1);

namespace App\Domain\Ticket\SLAEvaluator\Evaluators;

use App\Domain\Ticket\Models\Ticket;

class PremiumClientDecorator extends TicketSlaDecorator
{
    public function calculateResolutionWindow(Ticket $ticket): int
    {
        $hours = parent::calculateResolutionWindow($ticket);

        // VIP enterprise accounts get an immediate 12-hour SLA deduction
        if ($ticket->client->is_vip) {
            return max(2, $hours - 12); // absolute minimum floor of 2 hours
        }

        return $hours;
    }
}

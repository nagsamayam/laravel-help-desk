<?php

declare(strict_types=1);

namespace App\Domain\Ticket\SLAEvaluator\Evaluators;

use App\Domain\Ticket\Models\Ticket;
use App\Domain\Ticket\SLAEvaluator\Contracts\TicketSlaEvaluator;

class BaseTicketSlaEvaluator implements TicketSlaEvaluator
{
    /**
     * Standard SLA resolution time is 48 business hours.
     */
    public function calculateResolutionWindow(Ticket $ticket): int
    {
        return 48;
    }
}

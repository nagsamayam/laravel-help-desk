<?php

declare(strict_types=1);

namespace App\Domain\Ticket\SLAEvaluator\Contracts;

use App\Domain\Ticket\Models\Ticket;

interface TicketSlaEvaluator
{
    /**
     * Calculate the allowed resolution time in hours.
     */
    public function calculateResolutionWindow(Ticket $ticket): int;
}

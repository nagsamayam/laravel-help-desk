<?php

declare(strict_types=1);

namespace App\Domain\Ticket\SLAEvaluator\Evaluators;

use App\Domain\Ticket\Models\Ticket;
use App\Domain\Ticket\SLAEvaluator\Contracts\TicketSlaEvaluator;

abstract class TicketSlaDecorator implements TicketSlaEvaluator
{
    public function __construct(
        protected TicketSlaEvaluator $evaluator
    ) {}

    public function calculateResolutionWindow(Ticket $ticket): int
    {
        return $this->evaluator->calculateResolutionWindow($ticket);
    }
}

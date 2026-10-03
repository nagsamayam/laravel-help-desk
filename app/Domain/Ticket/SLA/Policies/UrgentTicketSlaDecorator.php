<?php

declare(strict_types=1);

use App\Domain\Ticket\Enums\TicketPriority;
use App\Domain\Ticket\Models\Ticket;
use App\Domain\Ticket\SLA\Policies\AbstractSlaDecorator;
use App\Domain\Ticket\SLA\ValueObjects\SlaResult;

final class UrgentTicketSlaDecorator extends AbstractSlaDecorator
{
    #[Override]
    public function calculate(Ticket $ticket): SlaResult
    {
        $sla = parent::calculate($ticket);

        if ($ticket->priority !== TicketPriority::Urgent) {
            return $sla;
        }

        return $sla->withResolutionMinutes(min($sla->resolutionMinutes, 240));
    }
}

<?php

declare(strict_types=1);

namespace App\Domain\Ticket\SLA\Policies;

use App\Domain\Ticket\Models\Ticket;
use App\Domain\Ticket\SLA\ValueObjects\SlaResult;
use Override;

final class VipCustomerSlaDecorator extends AbstractSlaDecorator
{
    #[Override]
    public function calculate(Ticket $ticket): SlaResult
    {
        $sla = parent::calculate($ticket);

        if (! $ticket->customer->isVip()) {
            return $sla;
        }

        return $sla->withResolutionMinutes(min($sla->resolutionMinutes, 480));
    }
}

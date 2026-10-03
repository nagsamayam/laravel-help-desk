<?php

declare(strict_types=1);

namespace App\Domain\Ticket\SLA\Policies;

use App\Domain\Ticket\Models\Ticket;
use App\Domain\Ticket\SLA\Contracts\SlaPolicy;
use App\Domain\Ticket\SLA\ValueObjects\SlaResult;
use Override;

abstract class AbstractSlaDecorator implements SlaPolicy
{
    public function __construct(private readonly SlaPolicy $base) {}

    #[Override]
    public function calculate(Ticket $ticket): SlaResult
    {
        return $this->base->calculate($ticket);
    }
}

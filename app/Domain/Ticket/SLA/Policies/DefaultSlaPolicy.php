<?php

declare(strict_types=1);

namespace App\Domain\Ticket\SLA\Policies;

use App\Domain\Ticket\Models\Ticket;
use App\Domain\Ticket\SLA\Contracts\SlaPolicy;
use App\Domain\Ticket\SLA\ValueObjects\SlaResult;
use Override;

class DefaultSlaPolicy implements SlaPolicy
{
    #[Override]
    public function calculate(Ticket $ticket): SlaResult
    {
        return new SlaResult(60, 1440);
    }
}

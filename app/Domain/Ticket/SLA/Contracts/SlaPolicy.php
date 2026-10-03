<?php

declare(strict_types=1);

namespace App\Domain\Ticket\SLA\Contracts;

use App\Domain\Ticket\Models\Ticket;
use App\Domain\Ticket\SLA\ValueObjects\SlaResult;

interface SlaPolicy
{
    public function calculate(Ticket $ticket): SlaResult;
}

<?php

declare(strict_types=1);

namespace App\Domain\Ticket\Repository\Contracts;

use App\Domain\Ticket\Models\Ticket;

interface TicketRepositoryInterface
{
    public function find(int $id): ?Ticket;
}

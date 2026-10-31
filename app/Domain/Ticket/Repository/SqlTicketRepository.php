<?php

declare(strict_types=1);

namespace App\Domain\Ticket\Repository;

use App\Domain\Ticket\Models\Ticket;
use App\Domain\Ticket\Repository\Contracts\TicketRepositoryInterface;

class SqlTicketRepository implements TicketRepositoryInterface
{
    public function find(int $id): ?Ticket
    {
        // Pure database interaction
        return Ticket::with(['client', 'assignee'])->find($id);
    }
}

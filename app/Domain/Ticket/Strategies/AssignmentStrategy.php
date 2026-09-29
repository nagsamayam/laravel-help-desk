<?php

declare(strict_types=1);

namespace App\Domain\Ticket\Strategies;

use App\Domain\Identity\Models\User;
use App\Domain\Ticket\Models\Ticket;
use Illuminate\Support\Collection;

interface AssignmentStrategy
{
    /**
     * Determine the agent to assign to the ticket.
     *
     * @param  Collection<int, User>|array<int, User>|null  $candidates
     */
    public function assign(Ticket $ticket, ?iterable $candidates = null): ?User;
}

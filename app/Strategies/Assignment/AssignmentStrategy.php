<?php

declare(strict_types=1);

namespace App\Strategies\Assignment;

use App\Models\Ticket;
use App\Models\User;
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

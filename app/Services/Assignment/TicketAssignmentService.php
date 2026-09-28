<?php

declare(strict_types=1);

namespace App\Services\Assignment;

use App\Models\Ticket;
use App\Models\User;
use App\Strategies\Assignment\AssignmentStrategy;
use App\Strategies\Assignment\RoundRobinAssignment;

final class TicketAssignmentService
{
    private AssignmentStrategy $strategy;

    public function __construct(?AssignmentStrategy $strategy = null)
    {
        $this->strategy = $strategy ?? new RoundRobinAssignment;
    }

    public function setStrategy(AssignmentStrategy $strategy): self
    {
        $this->strategy = $strategy;

        return $this;
    }

    public function getStrategy(): AssignmentStrategy
    {
        return $this->strategy;
    }

    /**
     * @param  iterable<int, User>|null  $candidates
     */
    public function assign(Ticket $ticket, ?AssignmentStrategy $strategy = null, ?iterable $candidates = null): ?User
    {
        $chosenStrategy = $strategy ?? $this->strategy;
        $agent = $chosenStrategy->assign($ticket, $candidates);

        if ($agent !== null) {
            $ticket->assigned_to = $agent->id;
            $ticket->save();
        }

        return $agent;
    }
}

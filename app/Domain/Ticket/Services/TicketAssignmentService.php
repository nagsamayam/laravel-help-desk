<?php

declare(strict_types=1);

namespace App\Domain\Ticket\Services;

use App\Domain\Identity\Models\User;
use App\Domain\Ticket\Models\Ticket;
use App\Domain\Ticket\Strategies\AssignmentStrategy;
use App\Domain\Ticket\Strategies\RoundRobinAssignment;

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

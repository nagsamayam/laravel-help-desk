<?php

declare(strict_types=1);

namespace App\Domain\Ticket\Specifications;

use App\Domain\Identity\Models\User;
use App\Domain\Ticket\Models\Ticket;
use Illuminate\Database\Eloquent\Builder;

final class AssignedToAgentSpecification extends TicketSpecification
{
    private readonly ?int $agentId;

    public function __construct(User|int|null $agent = null)
    {
        $this->agentId = $agent instanceof User ? $agent->id : $agent;
    }

    public function isSatisfiedBy(Ticket $ticket): bool
    {
        if ($this->agentId === null) {
            return $ticket->assigned_to !== null;
        }

        return $ticket->assigned_to === $this->agentId;
    }

    /**
     * @param  Builder<Ticket>  $query
     * @return Builder<Ticket>
     */
    public function apply(Builder $query): Builder
    {
        if ($this->agentId === null) {
            return $query->whereNotNull('assigned_to');
        }

        return $query->where('assigned_to', $this->agentId);
    }
}

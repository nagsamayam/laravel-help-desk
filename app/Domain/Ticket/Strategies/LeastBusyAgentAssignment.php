<?php

declare(strict_types=1);

namespace App\Domain\Ticket\Strategies;

use App\Domain\Identity\Enums\Role;
use App\Domain\Identity\Models\User;
use App\Domain\Ticket\Enums\TicketStatus;
use App\Domain\Ticket\Models\Ticket;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

final class LeastBusyAgentAssignment implements AssignmentStrategy
{
    /**
     * @param  Collection<int, User>|array<int, User>|null  $candidates
     */
    public function assign(Ticket $ticket, ?iterable $candidates = null): ?User
    {
        if ($candidates !== null) {
            $agents = collect($candidates)->values();
            if ($agents->isEmpty()) {
                return null;
            }

            $agentIds = $agents->pluck('id')->filter()->all();

            $counts = Ticket::query()
                ->whereIn('assigned_to', $agentIds)
                ->whereIn('status', $this->activeStatuses())
                ->selectRaw('assigned_to, count(*) as active_count')
                ->groupBy('assigned_to')
                ->pluck('active_count', 'assigned_to')
                ->all();

            return $agents
                ->sortBy([
                    fn (User $a, User $b) => ($counts[$a->id] ?? 0) <=> ($counts[$b->id] ?? 0),
                    fn (User $a, User $b) => $a->id <=> $b->id,
                ])
                ->first();
        }

        return User::query()
            ->where('role', Role::Agent)
            ->withCount(['assignedTickets as active_tickets_count' => function (Builder $query): void {
                $query->whereIn('status', $this->activeStatuses());
            }])
            ->orderBy('active_tickets_count', 'asc')
            ->orderBy('id', 'asc')
            ->first();
    }

    /**
     * @return array<int, TicketStatus>
     */
    private function activeStatuses(): array
    {
        return [
            TicketStatus::Open,
            TicketStatus::InProgess,
            TicketStatus::WaitingForCustomer,
        ];
    }
}

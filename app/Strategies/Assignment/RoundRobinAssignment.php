<?php

declare(strict_types=1);

namespace App\Strategies\Assignment;

use App\Enums\Role;
use App\Models\Ticket;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;

final class RoundRobinAssignment implements AssignmentStrategy
{
    public function __construct(
        private readonly string $cacheKey = 'ticket_assignment:round_robin:pointer',
    ) {}

    /**
     * @param  Collection<int, User>|array<int, User>|null  $candidates
     */
    public function assign(Ticket $ticket, ?iterable $candidates = null): ?User
    {
        $agents = $this->resolveCandidates($candidates);

        if ($agents->isEmpty()) {
            return null;
        }

        $count = $agents->count();
        $pointer = (int) Cache::increment($this->cacheKey);
        $index = ($pointer - 1) % $count;

        return $agents->values()->get($index);
    }

    /**
     * @param  Collection<int, User>|array<int, User>|null  $candidates
     * @return Collection<int, User>
     */
    private function resolveCandidates(?iterable $candidates): Collection
    {
        if ($candidates !== null) {
            return collect($candidates)->values();
        }

        return User::query()
            ->where('role', Role::Agent)
            ->orderBy('id')
            ->get();
    }
}

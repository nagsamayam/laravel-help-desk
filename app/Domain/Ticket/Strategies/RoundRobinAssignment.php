<?php

declare(strict_types=1);

namespace App\Domain\Ticket\Strategies;

use App\Domain\Identity\Enums\Role;
use App\Domain\Identity\Models\User;
use App\Domain\Ticket\Models\Ticket;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Throwable;

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

        try {
            // Standard Atomic Operations Flow
            $count = $agents->count();
            $pointer = (int) Cache::increment($this->cacheKey);
            $index = ($pointer - 1) % $count;

            return $agents->values()->get($index);
        } catch (Throwable $e) {
            // Fail-safe Mechanism triggered if Redis/Memcached goes down completely
            Log::critical('Ticket assignment cache connection failed. Falling back to random selection.', [
                'exception' => $e->getMessage(),
                'ticket_id' => $ticket->id,
            ]);

            // Emergency Fallback: Select a random agent so business operations don't freeze
            return $agents->random();
        }
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

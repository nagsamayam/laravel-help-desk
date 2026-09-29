<?php

declare(strict_types=1);

namespace App\Domain\Ticket\Strategies;

use App\Domain\Identity\Enums\Role;
use App\Domain\Identity\Models\User;
use App\Domain\Ticket\Models\Ticket;
use Closure;
use Illuminate\Support\Collection;

final class SkillBasedAssignment implements AssignmentStrategy
{
    /**
     * @param  array<int, array<int|string>>  $agentSkillsMap  [agent_id => [category_id/skill_name, ...]]
     * @param  (Closure(Ticket, User): bool)|null  $skillMatcher
     */
    public function __construct(
        private readonly array $agentSkillsMap = [],
        private readonly ?Closure $skillMatcher = null,
        private readonly AssignmentStrategy $fallbackStrategy = new LeastBusyAgentAssignment,
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

        $qualifiedAgents = $agents->filter(function (User $agent) use ($ticket): bool {
            if ($this->skillMatcher !== null) {
                return (bool) ($this->skillMatcher)($ticket, $agent);
            }

            $skills = $this->agentSkillsMap[$agent->id] ?? [];

            if (empty($skills)) {
                return false;
            }

            $categoryMatch = in_array($ticket->category_id, $skills, true);
            $categoryNameMatch = $ticket->category !== null && in_array($ticket->category->name, $skills, true);

            return $categoryMatch || $categoryNameMatch;
        });

        if ($qualifiedAgents->isNotEmpty()) {
            return $this->fallbackStrategy->assign($ticket, $qualifiedAgents);
        }

        return $this->fallbackStrategy->assign($ticket, $agents);
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

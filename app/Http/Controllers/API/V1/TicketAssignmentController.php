<?php

declare(strict_types=1);

namespace App\Http\Controllers\API\V1;

use App\Domain\Identity\Models\User;
use App\Domain\Ticket\Actions\AssignTicketAction;
use App\Domain\Ticket\Events\TicketAssigned;
use App\Domain\Ticket\Models\Ticket;
use App\Domain\Ticket\Services\TicketAssignmentService;
use App\Domain\Ticket\Strategies\AssignmentStrategy;
use App\Domain\Ticket\Strategies\LeastBusyAgentAssignment;
use App\Domain\Ticket\Strategies\RoundRobinAssignment;
use App\Domain\Ticket\Strategies\SkillBasedAssignment;
use App\Http\Controllers\Controller;
use App\Http\Requests\V1\AssignTicketRequest;
use App\Http\Resources\V1\TicketResource;
use App\Http\Responses\IdempotencyResponse;
use App\Infrastructure\Idempotency\IdempotencyResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Gate;

final class TicketAssignmentController extends Controller
{
    public function assign(
        AssignTicketRequest $request,
        Ticket $ticket,
        TicketAssignmentService $assignmentService,
        AssignTicketAction $assignTicketAction,
    ): JsonResponse {
        Gate::authorize('assign', $ticket);

        $agentId = $request->input('agent_id') ?? $request->input('assigned_to');
        $strategyName = $request->input('strategy');

        if ($agentId !== null) {
            $agent = User::query()->findOrFail((int) $agentId);
            $assignedTicket = $assignTicketAction->execute($ticket, $agent);
            $assignedTicket->loadMissing(['category', 'customer', 'assignee']);

            return new IdempotencyResponse(
                data: [
                    'data' => (new TicketResource($assignedTicket))->resolve($request),
                    'message' => "Ticket assigned to agent [{$agent->full_name}].",
                ],
                status: Response::HTTP_OK,
                resource: new IdempotencyResource('ticket', $assignedTicket->getKey()),
            );
        }

        $previousAgentId = $ticket->assigned_to !== null ? (int) $ticket->assigned_to : null;
        $strategy = $this->resolveStrategy((string) $strategyName);
        $assignedAgent = $assignmentService->assign($ticket, $strategy);

        $freshTicket = $ticket->refresh();
        $freshTicket->loadMissing(['category', 'customer', 'assignee']);

        if ($assignedAgent === null) {
            return new IdempotencyResponse(
                data: [
                    'data' => (new TicketResource($freshTicket))->resolve($request),
                    'message' => 'No suitable agent was available for assignment.',
                ],
                status: Response::HTTP_OK,
                resource: new IdempotencyResource('ticket', $freshTicket->getKey()),
            );
        }

        TicketAssigned::dispatch($freshTicket, $assignedAgent, $previousAgentId);

        return new IdempotencyResponse(
            data: [
                'data' => (new TicketResource($freshTicket))->resolve($request),
                'message' => "Ticket automatically assigned to [{$assignedAgent->full_name}] via [{$strategyName}] strategy.",
            ],
            status: Response::HTTP_OK,
            resource: new IdempotencyResource('ticket', $freshTicket->getKey()),
        );
    }

    private function resolveStrategy(string $strategy): AssignmentStrategy
    {
        return match ($strategy) {
            'least_busy' => new LeastBusyAgentAssignment,
            'skill_based' => new SkillBasedAssignment,
            default => new RoundRobinAssignment,
        };
    }
}

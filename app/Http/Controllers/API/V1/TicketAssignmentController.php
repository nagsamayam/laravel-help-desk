<?php

declare(strict_types=1);

namespace App\Http\Controllers\API\V1;

use App\Actions\AssignTicketAction;
use App\Events\Tickets\TicketAssigned;
use App\Http\Controllers\Controller;
use App\Http\Requests\V1\AssignTicketRequest;
use App\Http\Resources\V1\TicketResource;
use App\Models\Ticket;
use App\Models\User;
use App\Services\Assignment\TicketAssignmentService;
use App\Strategies\Assignment\AssignmentStrategy;
use App\Strategies\Assignment\LeastBusyAgentAssignment;
use App\Strategies\Assignment\RoundRobinAssignment;
use App\Strategies\Assignment\SkillBasedAssignment;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;

final class TicketAssignmentController extends Controller
{
    public function assign(
        AssignTicketRequest $request,
        Ticket $ticket,
        TicketAssignmentService $assignmentService,
        AssignTicketAction $assignTicketAction,
    ): JsonResponse {
        $agentId = $request->input('agent_id');
        $strategyName = $request->input('strategy');

        if ($agentId !== null) {
            $agent = User::query()->findOrFail((int) $agentId);
            $assignedTicket = $assignTicketAction->execute($ticket, $agent);

            return response()->json([
                'data' => (new TicketResource($assignedTicket))->resolve($request),
                'message' => "Ticket assigned to agent [{$agent->full_name}].",
            ], Response::HTTP_OK);
        }

        $previousAgentId = $ticket->assigned_to !== null ? (int) $ticket->assigned_to : null;
        $strategy = $this->resolveStrategy((string) $strategyName);
        $assignedAgent = $assignmentService->assign($ticket, $strategy);

        $freshTicket = $ticket->refresh();

        if ($assignedAgent === null) {
            return response()->json([
                'data' => (new TicketResource($freshTicket))->resolve($request),
                'message' => 'No suitable agent was available for assignment.',
            ], Response::HTTP_OK);
        }

        TicketAssigned::dispatch($freshTicket, $assignedAgent, $previousAgentId);

        return response()->json([
            'data' => (new TicketResource($freshTicket))->resolve($request),
            'message' => "Ticket automatically assigned to [{$assignedAgent->full_name}] via [{$strategyName}] strategy.",
        ], Response::HTTP_OK);
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

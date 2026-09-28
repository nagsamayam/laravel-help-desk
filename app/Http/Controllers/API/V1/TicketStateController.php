<?php

declare(strict_types=1);

namespace App\Http\Controllers\API\V1;

use App\Actions\CloseTicketAction;
use App\Actions\ReopenTicketAction;
use App\Actions\ResolveTicketAction;
use App\Enums\Role;
use App\Enums\TicketStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\V1\TransitionTicketRequest;
use App\Http\Resources\V1\TicketResource;
use App\Models\Ticket;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

final class TicketStateController extends Controller
{
    public function transition(
        TransitionTicketRequest $request,
        Ticket $ticket,
    ): JsonResponse {
        $targetStatus = TicketStatus::from((string) $request->validated('status'));

        $updated = $ticket->transitionTo($targetStatus);

        return response()->json([
            'data' => (new TicketResource($updated))->resolve($request),
            'message' => "Ticket transitioned to {$targetStatus->value}.",
        ], Response::HTTP_OK);
    }

    public function resolve(
        Request $request,
        Ticket $ticket,
        ResolveTicketAction $action,
    ): JsonResponse {
        $resolvedTicket = $action->execute($ticket);

        return response()->json([
            'data' => (new TicketResource($resolvedTicket))->resolve($request),
            'message' => 'Ticket resolved successfully.',
        ], Response::HTTP_OK);
    }

    public function close(
        Request $request,
        Ticket $ticket,
        CloseTicketAction $action,
    ): JsonResponse {
        $user = $request->user();

        if ($user !== null && $user->hasRole(Role::Customer) && $ticket->customer_id !== $user->id) {
            abort(Response::HTTP_FORBIDDEN, 'You are not authorized to close this ticket.');
        }

        $closedTicket = $action->execute($ticket);

        return response()->json([
            'data' => (new TicketResource($closedTicket))->resolve($request),
            'message' => 'Ticket closed successfully.',
        ], Response::HTTP_OK);
    }

    public function reopen(
        Request $request,
        Ticket $ticket,
        ReopenTicketAction $action,
    ): JsonResponse {
        $user = $request->user();

        if ($user !== null && $user->hasRole(Role::Customer) && $ticket->customer_id !== $user->id) {
            abort(Response::HTTP_FORBIDDEN, 'You are not authorized to reopen this ticket.');
        }

        $reopenedTicket = $action->execute($ticket);

        return response()->json([
            'data' => (new TicketResource($reopenedTicket))->resolve($request),
            'message' => 'Ticket reopened successfully.',
        ], Response::HTTP_OK);
    }
}

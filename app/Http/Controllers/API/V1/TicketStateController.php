<?php

declare(strict_types=1);

namespace App\Http\Controllers\API\V1;

use App\Domain\Ticket\Actions\CloseTicketAction;
use App\Domain\Ticket\Actions\ReopenTicketAction;
use App\Domain\Ticket\Actions\ResolveTicketAction;
use App\Domain\Ticket\Enums\TicketStatus;
use App\Domain\Ticket\Models\Ticket;
use App\Http\Controllers\Controller;
use App\Http\Requests\V1\TransitionTicketRequest;
use App\Http\Resources\V1\TicketResource;
use App\Infrastructure\Idempotency\IdempotencyResource;
use App\Infrastructure\Idempotency\IdempotencyResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Gate;

final class TicketStateController extends Controller
{
    public function transition(
        TransitionTicketRequest $request,
        Ticket $ticket,
    ): JsonResponse {
        Gate::authorize('transition', $ticket);

        $targetStatus = TicketStatus::from((string) $request->validated('status'));

        $updated = $ticket->transitionTo($targetStatus);
        $updated->loadMissing(['category', 'customer', 'assignee']);

        return new IdempotencyResponse(
            data: [
                'data' => (new TicketResource($updated))->resolve($request),
                'message' => "Ticket transitioned to {$targetStatus->value}.",
            ],
            status: Response::HTTP_OK,
            resource: new IdempotencyResource('ticket', $updated->getKey()),
        );
    }

    public function resolve(
        Request $request,
        Ticket $ticket,
        ResolveTicketAction $action,
    ): JsonResponse {
        Gate::authorize('resolve', $ticket);

        $resolvedTicket = $action->execute($ticket);
        $resolvedTicket->loadMissing(['category', 'customer', 'assignee']);

        return new IdempotencyResponse(
            data: [
                'data' => (new TicketResource($resolvedTicket))->resolve($request),
                'message' => 'Ticket resolved successfully.',
            ],
            status: Response::HTTP_OK,
            resource: new IdempotencyResource('ticket', $resolvedTicket->getKey()),
        );
    }

    public function close(
        Request $request,
        Ticket $ticket,
        CloseTicketAction $action,
    ): JsonResponse {
        Gate::authorize('close', $ticket);

        $closedTicket = $action->execute($ticket);
        $closedTicket->loadMissing(['category', 'customer', 'assignee']);

        return new IdempotencyResponse(
            data: [
                'data' => (new TicketResource($closedTicket))->resolve($request),
                'message' => 'Ticket closed successfully.',
            ],
            status: Response::HTTP_OK,
            resource: new IdempotencyResource('ticket', $closedTicket->getKey()),
        );
    }

    public function reopen(
        Request $request,
        Ticket $ticket,
        ReopenTicketAction $action,
    ): JsonResponse {
        Gate::authorize('reopen', $ticket);

        $reopenedTicket = $action->execute($ticket);
        $reopenedTicket->loadMissing(['category', 'customer', 'assignee']);

        return new IdempotencyResponse(
            data: [
                'data' => (new TicketResource($reopenedTicket))->resolve($request),
                'message' => 'Ticket reopened successfully.',
            ],
            status: Response::HTTP_OK,
            resource: new IdempotencyResource('ticket', $reopenedTicket->getKey()),
        );
    }
}

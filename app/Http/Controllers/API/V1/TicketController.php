<?php

declare(strict_types=1);

namespace App\Http\Controllers\API\V1;

use App\Actions\CreateTicketAction;
use App\Actions\DeleteTicketAction;
use App\Actions\UpdateTicketAction;
use App\DTOs\CreateTicketData;
use App\Enums\TicketPriority;
use App\Http\Controllers\Controller;
use App\Http\Requests\V1\CreateTicketRequest;
use App\Http\Requests\V1\TicketRequest;
use App\Http\Requests\V1\UpdateTicketRequest;
use App\Http\Resources\V1\TicketResource;
use App\Models\Ticket;
use App\Support\Idempotency\IdempotencyManager;
use App\Support\Idempotency\IdempotencyResult;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;

final class TicketController extends Controller
{
    public function store(
        CreateTicketRequest $request,
        CreateTicketAction $createTicket,
        IdempotencyManager $idempotency
    ): JsonResponse {
        $user = $request->user();
        $validated = $request->validated();

        $ticketData = CreateTicketData::from([
            'subject' => $request->string('subject'),
            'description' => $request->string('description'),
            'customer_id' => $user->getKey(),
            'priority' => $request->enum('priority', TicketPriority::class),
            'category_id' => $request->integer('category_id'),
        ]);

        $result = $idempotency->execute(
            scopeType: 'user',
            scopeId: $user->getKey(),
            operation: 'tickets.create',
            key: $request->idempotencyKey(),
            requestPayload: $validated,
            callback: function () use ($request, $createTicket, $ticketData): IdempotencyResult {
                $ticket = $createTicket->execute($ticketData);

                return new IdempotencyResult(
                    status: Response::HTTP_CREATED,
                    body: ['data' => (new TicketResource($ticket))->resolve($request)],
                    replayed: false,
                    resourceType: 'ticket',
                    resourceId: $ticket->getKey()
                );
            }
        );

        return response()
            ->json($result->body, $result->status)
            ->header('Idempotency-Replayed', $result->replayed ? 'true' : 'false');
    }

    public function update(
        UpdateTicketRequest $request,
        int $ticketId,
        UpdateTicketAction $updateTicketAction,
        IdempotencyManager $idempotency,
    ): JsonResponse {
        $validated = $request->validated();

        $result = $idempotency->execute(
            scopeType: 'user',
            scopeId: $request->user()->getKey(),
            operation: 'tickets.update',
            key: $request->idempotencyKey(),
            requestPayload: $validated,
            callback: function () use ($request, $ticketId, $updateTicketAction, $validated): IdempotencyResult {
                // Fresh database fetch inside the transaction retry block ensures fresh state
                $ticket = Ticket::query()->findOrFail($ticketId);

                $updatedTicket = $updateTicketAction->execute(
                    ticket: $ticket,
                    attributes: $validated,
                );

                return new IdempotencyResult(
                    status: Response::HTTP_OK,
                    body: ['data' => (new TicketResource($updatedTicket))->resolve($request)],
                    replayed: false,
                    resourceType: 'ticket',
                    resourceId: $updatedTicket->getKey(),
                );
            },
        );

        return response()
            ->json($result->body, $result->status)
            ->header('Idempotency-Replayed', $result->replayed ? 'true' : 'false');
    }

    public function destroy(
        TicketRequest $request,
        int $ticketId,
        DeleteTicketAction $deleteTicketAction,
        IdempotencyManager $idempotency,
    ): JsonResponse {
        $result = $idempotency->execute(
            scopeType: 'user',
            scopeId: $request->user()->getKey(),
            operation: 'tickets.delete',
            key: $request->idempotencyKey(),
            requestPayload: ['ticket_id' => $ticketId],
            callback: function () use ($ticketId, $deleteTicketAction): IdempotencyResult {
                // Use findWithTrashed() or conditional lookup to maintain idempotency after deletion
                $ticket = Ticket::query()->find($ticketId);

                // If it's already missing/deleted, return a clean success array directly to satisfy the retry replay contract
                if ($ticket === null) {
                    return new IdempotencyResult(
                        status: Response::HTTP_OK,
                        body: ['message' => 'Ticket deleted successfully.'],
                        replayed: false,
                        resourceType: 'ticket',
                        resourceId: $ticketId,
                    );
                }

                $deleteTicketAction->execute($ticket);

                return new IdempotencyResult(
                    status: Response::HTTP_OK,
                    body: ['message' => 'Ticket deleted successfully.'],
                    replayed: false,
                    resourceType: 'ticket',
                    resourceId: $ticketId,
                );
            },
        );

        return response()
            ->json($result->body, $result->status)
            ->header('Idempotency-Replayed', $result->replayed ? 'true' : 'false');
    }

    public function show(Ticket $ticket): TicketResource
    {
        return new TicketResource($ticket);
    }
}

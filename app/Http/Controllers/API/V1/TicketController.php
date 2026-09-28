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
    public function store(CreateTicketRequest $request, CreateTicketAction $createTicket, IdempotencyManager $idempotency): JsonResponse
    {

        $ticketData = CreateTicketData::from([
            'idempotency_key' => $request->idempotencyKey(),
            'subject' => $request->string('subject'),
            'description' => $request->string('description'),
            'customer_id' => $request->user()->getKey(),
            'priority' => $request->enum('priority', TicketPriority::class),
            'category_id' => $request->integer('category_id'),
        ]);

        $result = $idempotency->execute(scopeType: 'user', scopeId: (int) $request->user()->getKey(), operation: 'tickets.create', key: $request->idempotencyKey(), requestPayload: $request->validated(), callback: function () use ($request, $createTicket, $ticketData): IdempotencyResult {
            $ticket = $createTicket->execute($ticketData);

            return new IdempotencyResult(
                status: Response::HTTP_CREATED,
                body: ['data' => (new TicketResource($ticket))->resolve($request)],
                replayed: false,
                resourceType: 'ticket',
                resourceId: $ticket->getKey()
            );
        }, );

        return response()
            ->json($result->body, $result->status)
            ->header('Idempotency-Replayed', $result->replayed ? 'true' : 'false');
    }

    public function update(
        UpdateTicketRequest $request,
        Ticket $ticket,
        UpdateTicketAction $updateTicketAction,
        IdempotencyManager $idempotency,
    ): JsonResponse {
        $result = $idempotency->execute(
            scopeType: 'user',
            scopeId: (int) $request->user()->getKey(),
            operation: 'tickets.update',
            key: $request->idempotencyKey(),
            requestPayload: $request->validated(),
            callback: function () use (
                $request,
                $ticket,
                $updateTicketAction,
            ): IdempotencyResult {
                $ticket = $updateTicketAction->execute(
                    ticket: $ticket,
                    attributes: $request->validated(),
                );

                return new IdempotencyResult(
                    status: 200,
                    body: [
                        'data' => (new TicketResource($ticket))->resolve($request),
                    ],
                    replayed: false,
                    resourceType: 'ticket',
                    resourceId: $ticket->getKey(),
                );
            },
        );

        return response()
            ->json($result->body, $result->status)
            ->header(
                'Idempotency-Replayed',
                $result->replayed ? 'true' : 'false',
            );
    }

    public function destroy(
        TicketRequest $request,
        int $ticketId,
        DeleteTicketAction $deleteTicketAction,
        IdempotencyManager $idempotency,
    ): JsonResponse {
        $result = $idempotency->execute(
            scopeType: 'user',
            scopeId: (int) $request->user()->getKey(),
            operation: 'tickets.delete',
            key: trim((string) $request->header('Idempotency-Key')),
            requestPayload: [
                'ticket_id' => $ticketId,
            ],
            callback: function () use (
                $ticketId,
                $deleteTicketAction,
            ): IdempotencyResult {
                $ticket = Ticket::query()->findOrFail($ticketId);

                $deleteTicketAction->execute($ticket);

                return new IdempotencyResult(
                    status: 200,
                    body: [
                        'message' => 'Ticket deleted successfully.',
                    ],
                    replayed: false,
                    resourceType: 'ticket',
                    resourceId: $ticketId,
                );
            },
        );

        return response()
            ->json($result->body, $result->status)
            ->header(
                'Idempotency-Replayed',
                $result->replayed ? 'true' : 'false',
            );
    }

    public function show(Ticket $ticket)
    {
        return new TicketResource($ticket);
    }
}

<?php

declare(strict_types=1);

namespace App\Http\Controllers\API\V1;

use App\Actions\CreateTicketAction;
use App\Actions\DeleteTicketAction;
use App\Actions\UpdateTicketAction;
use App\DTOs\CreateTicketData;
use App\DTOs\UpdateTicketData;
use App\Http\Controllers\Controller;
use App\Http\Requests\V1\CreateTicketRequest;
use App\Http\Requests\V1\TicketRequest;
use App\Http\Requests\V1\UpdateTicketRequest;
use App\Http\Resources\V1\TicketResource;
use App\Models\Ticket;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;

final class TicketController extends Controller
{
    public function store(
        CreateTicketRequest $request,
        CreateTicketAction $createTicket,
    ): JsonResponse {
        $user = $request->user();

        $ticketData = CreateTicketData::from([
            ...$request->validated(),
            'customer_id' => $user->getKey(),
        ]);

        $ticket = $createTicket->execute($ticketData);

        return response()->json([
            'data' => (new TicketResource($ticket))->resolve($request),
        ], Response::HTTP_CREATED);
    }

    public function update(
        UpdateTicketRequest $request,
        int $ticketId,
        UpdateTicketAction $updateTicketAction,
    ): JsonResponse {
        $ticket = Ticket::query()->findOrFail($ticketId);

        $ticketData = UpdateTicketData::from($request->validated());

        $updatedTicket = $updateTicketAction->execute(
            ticket: $ticket,
            attributes: $ticketData,
        );

        return response()->json([
            'data' => (new TicketResource($updatedTicket))->resolve($request),
        ], Response::HTTP_OK);
    }

    public function destroy(
        TicketRequest $request,
        int $ticketId,
        DeleteTicketAction $deleteTicketAction,
    ): JsonResponse {
        $ticket = Ticket::query()->find($ticketId);

        if ($ticket !== null) {
            $deleteTicketAction->execute($ticket);
        }

        return response()->json([
            'message' => 'Ticket deleted successfully.',
        ], Response::HTTP_OK);
    }

    public function show(Ticket $ticket): TicketResource
    {
        return new TicketResource($ticket);
    }
}

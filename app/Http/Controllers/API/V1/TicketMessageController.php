<?php

declare(strict_types=1);

namespace App\Http\Controllers\API\V1;

use App\Domain\Identity\Enums\Role;
use App\Domain\Ticket\Actions\AddTicketMessageAction;
use App\Domain\Ticket\Models\Ticket;
use App\Http\Controllers\Controller;
use App\Http\Requests\V1\CreateTicketMessageRequest;
use App\Http\Resources\V1\TicketMessageResource;
use App\Infrastructure\Idempotency\IdempotencyResource;
use App\Infrastructure\Idempotency\IdempotencyResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Gate;

final class TicketMessageController extends Controller
{
    public function index(Request $request, Ticket $ticket): AnonymousResourceCollection
    {
        Gate::authorize('viewMessages', $ticket);

        $user = $request->user();
        $query = $ticket->messages()->with('user');

        if ($user !== null && $user->hasRole(Role::Customer)) {
            $query->where('is_internal', false);
        }

        $messages = $query->oldest()->paginate(30);

        return TicketMessageResource::collection($messages);
    }

    public function store(
        CreateTicketMessageRequest $request,
        Ticket $ticket,
        AddTicketMessageAction $action,
    ): JsonResponse {
        Gate::authorize('addMessage', $ticket);

        $user = $request->user();

        $isInternal = false;
        if ($request->boolean('is_internal')) {
            Gate::authorize('addInternalNote', $ticket);
            $isInternal = true;
        }

        $message = $action->execute(
            ticket: $ticket,
            user: $user,
            message: (string) $request->validated('message'),
            isInternal: $isInternal,
        );

        return new IdempotencyResponse(
            data: [
                'data' => (new TicketMessageResource($message))->resolve($request),
                'message' => 'Ticket message added successfully.',
            ],
            status: Response::HTTP_CREATED,
            resource: new IdempotencyResource('ticket_message', $message->getKey()),
        );
    }
}

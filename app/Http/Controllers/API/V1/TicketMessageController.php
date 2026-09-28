<?php

declare(strict_types=1);

namespace App\Http\Controllers\API\V1;

use App\Actions\AddTicketMessageAction;
use App\Enums\Role;
use App\Http\Controllers\Controller;
use App\Http\Requests\V1\CreateTicketMessageRequest;
use App\Http\Resources\V1\TicketMessageResource;
use App\Models\Ticket;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;

final class TicketMessageController extends Controller
{
    public function index(Request $request, Ticket $ticket): AnonymousResourceCollection
    {
        $user = $request->user();

        if ($user !== null && $user->hasRole(Role::Customer) && $ticket->customer_id !== $user->id) {
            abort(Response::HTTP_FORBIDDEN, 'You are not authorized to view messages for this ticket.');
        }

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
        $user = $request->user();

        if ($user !== null && $user->hasRole(Role::Customer)) {
            if ($ticket->customer_id !== $user->id) {
                abort(Response::HTTP_FORBIDDEN, 'You are not authorized to add messages to this ticket.');
            }
            $isInternal = false;
        } else {
            $isInternal = (bool) $request->input('is_internal', false);
        }

        $message = $action->execute(
            ticket: $ticket,
            user: $user,
            message: (string) $request->validated('message'),
            isInternal: $isInternal,
        );

        return response()->json([
            'data' => (new TicketMessageResource($message))->resolve($request),
            'message' => 'Ticket message added successfully.',
        ], Response::HTTP_CREATED);
    }
}

<?php

declare(strict_types=1);

namespace App\Http\Controllers\API\V1;

use App\Actions\CreateTicketAction;
use App\Actions\DeleteTicketAction;
use App\Actions\UpdateTicketAction;
use App\DTOs\CreateTicketData;
use App\DTOs\UpdateTicketData;
use App\Enums\Role;
use App\Enums\TicketPriority;
use App\Enums\TicketStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\V1\CreateTicketRequest;
use App\Http\Requests\V1\FilterTicketsRequest;
use App\Http\Requests\V1\TicketRequest;
use App\Http\Requests\V1\UpdateTicketRequest;
use App\Http\Resources\V1\TicketResource;
use App\Models\Ticket;
use App\Specifications\Ticket\AssignedToAgentSpecification;
use App\Specifications\Ticket\CustomerTicketsSpecification;
use App\Specifications\Ticket\OpenTicketSpecification;
use App\Specifications\Ticket\OverdueTicketSpecification;
use App\Specifications\Ticket\PriorityTicketSpecification;
use App\Specifications\Ticket\StatusTicketSpecification;
use App\Specifications\Ticket\TicketSpecification;
use App\Specifications\Ticket\UnassignedTicketSpecification;
use App\Specifications\Ticket\UrgentTicketSpecification;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Gate;

final class TicketController extends Controller
{
    public function index(FilterTicketsRequest $request): AnonymousResourceCollection
    {
        Gate::authorize('viewAny', Ticket::class);

        $user = $request->user();
        $query = Ticket::query()->with(['category', 'customer', 'assignee']);

        /** @var TicketSpecification|null $spec */
        $spec = null;

        if ($user !== null && $user->hasRole(Role::Customer)) {
            $customerSpec = new CustomerTicketsSpecification((int) $user->id);
            $spec = $customerSpec;
        } elseif ($request->filled('customer_id')) {
            $customerSpec = new CustomerTicketsSpecification((int) $request->input('customer_id'));
            $spec = $spec ? $spec->and($customerSpec) : $customerSpec;
        }

        if ($request->filled('status')) {
            $statusSpec = new StatusTicketSpecification(TicketStatus::from((string) $request->input('status')));
            $spec = $spec ? $spec->and($statusSpec) : $statusSpec;
        }

        if ($request->boolean('open')) {
            $openSpec = new OpenTicketSpecification;
            $spec = $spec ? $spec->and($openSpec) : $openSpec;
        }

        if ($request->filled('priority')) {
            $prioritySpec = new PriorityTicketSpecification(TicketPriority::from((string) $request->input('priority')));
            $spec = $spec ? $spec->and($prioritySpec) : $prioritySpec;
        }

        if ($request->boolean('urgent')) {
            $urgentSpec = new UrgentTicketSpecification;
            $spec = $spec ? $spec->and($urgentSpec) : $urgentSpec;
        }

        if ($request->boolean('unassigned')) {
            $unassignedSpec = new UnassignedTicketSpecification;
            $spec = $spec ? $spec->and($unassignedSpec) : $unassignedSpec;
        } elseif ($request->filled('assigned_to')) {
            $assignedSpec = new AssignedToAgentSpecification((int) $request->input('assigned_to'));
            $spec = $spec ? $spec->and($assignedSpec) : $assignedSpec;
        }

        if ($request->boolean('overdue')) {
            $overdueDays = $request->integer('overdue_days', 3);
            $overdueSpec = new OverdueTicketSpecification(Carbon::now()->subDays($overdueDays));
            $spec = $spec ? $spec->and($overdueSpec) : $overdueSpec;
        }

        if ($spec !== null) {
            $query->matching($spec);
        }

        $perPage = (int) $request->input('per_page', 15);
        $tickets = $query->latest('id')->paginate($perPage);

        return TicketResource::collection($tickets);
    }

    public function store(
        CreateTicketRequest $request,
        CreateTicketAction $createTicket,
    ): JsonResponse {
        Gate::authorize('create', Ticket::class);

        $user = $request->user();

        $ticketData = CreateTicketData::from([
            ...$request->validated(),
            'customer_id' => $user->getKey(),
        ]);

        $ticket = $createTicket->execute($ticketData);
        $ticket->loadMissing(['category', 'customer', 'assignee']);

        return response()->json([
            'data' => (new TicketResource($ticket))->resolve($request),
        ], Response::HTTP_CREATED);
    }

    public function update(
        UpdateTicketRequest $request,
        Ticket $ticket,
        UpdateTicketAction $updateTicketAction,
    ): JsonResponse {
        Gate::authorize('update', $ticket);

        $ticketData = UpdateTicketData::from($request->validated());

        $updatedTicket = $updateTicketAction->execute(
            ticket: $ticket,
            attributes: $ticketData,
        );
        $updatedTicket->loadMissing(['category', 'customer', 'assignee']);

        return response()->json([
            'data' => (new TicketResource($updatedTicket))->resolve($request),
        ], Response::HTTP_OK);
    }

    public function destroy(
        TicketRequest $request,
        Ticket $ticket,
        DeleteTicketAction $deleteTicketAction,
    ): JsonResponse {
        Gate::authorize('delete', $ticket);

        $deleteTicketAction->execute($ticket);

        return response()->json([
            'message' => 'Ticket deleted successfully.',
        ], Response::HTTP_OK);
    }

    public function show(Ticket $ticket): TicketResource
    {
        Gate::authorize('view', $ticket);

        $ticket->loadMissing(['category', 'customer', 'assignee']);

        return new TicketResource($ticket);
    }
}

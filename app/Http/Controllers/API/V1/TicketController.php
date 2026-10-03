<?php

declare(strict_types=1);

namespace App\Http\Controllers\API\V1;

use App\Domain\Identity\Enums\Role;
use App\Domain\Ticket\Actions\CreateTicketAction;
use App\Domain\Ticket\Actions\DeleteTicketAction;
use App\Domain\Ticket\Actions\UpdateTicketAction;
use App\Domain\Ticket\DTOs\CreateTicketData;
use App\Domain\Ticket\DTOs\UpdateTicketData;
use App\Domain\Ticket\Enums\TicketPriority;
use App\Domain\Ticket\Enums\TicketStatus;
use App\Domain\Ticket\Models\Ticket;
use App\Domain\Ticket\Specifications\AssignedToAgentSpecification;
use App\Domain\Ticket\Specifications\CustomerTicketsSpecification;
use App\Domain\Ticket\Specifications\OpenTicketSpecification;
use App\Domain\Ticket\Specifications\OverdueTicketSpecification;
use App\Domain\Ticket\Specifications\PriorityTicketSpecification;
use App\Domain\Ticket\Specifications\StatusTicketSpecification;
use App\Domain\Ticket\Specifications\TicketSpecification;
use App\Domain\Ticket\Specifications\UnassignedTicketSpecification;
use App\Domain\Ticket\Specifications\UrgentTicketSpecification;
use App\Http\Controllers\Controller;
use App\Http\Requests\V1\CreateTicketRequest;
use App\Http\Requests\V1\FilterTicketsRequest;
use App\Http\Requests\V1\TicketRequest;
use App\Http\Requests\V1\UpdateTicketRequest;
use App\Http\Resources\V1\TicketResource;
use App\Http\Responses\IdempotencyResponse;
use App\Infrastructure\Idempotency\IdempotencyResource;
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
        $ticket->loadMissing(['category', 'customer', 'assignee', 'attachments']);

        return new IdempotencyResponse(
            data: [
                'data' => (new TicketResource($ticket))->resolve($request),
            ],
            status: Response::HTTP_CREATED,
            resource: new IdempotencyResource('ticket', $ticket->getKey()),
        );
    }

    public function update(
        UpdateTicketRequest $request,
        string $ticket,
        UpdateTicketAction $updateTicketAction,
    ): JsonResponse {
        $ticket = Ticket::query()->findOrFail($ticket);
        Gate::authorize('update', $ticket);

        $ticketData = UpdateTicketData::from($request->validated());

        $updatedTicket = $updateTicketAction->execute(
            ticket: $ticket,
            attributes: $ticketData,
        );
        $updatedTicket->loadMissing(['category', 'customer', 'assignee', 'attachments']);

        return new IdempotencyResponse(
            data: [
                'data' => (new TicketResource($updatedTicket))->resolve($request),
            ],
            status: Response::HTTP_OK,
            resource: new IdempotencyResource('ticket', $updatedTicket->getKey()),
        );
    }

    public function destroy(
        TicketRequest $request,
        string $ticket,
        DeleteTicketAction $deleteTicketAction,
    ): JsonResponse {
        $ticket = Ticket::query()->findOrFail($ticket);
        Gate::authorize('delete', $ticket);

        $deleteTicketAction->execute($ticket);

        return new IdempotencyResponse(
            data: [
                'message' => 'Ticket deleted successfully.',
            ],
            status: Response::HTTP_OK,
            resource: new IdempotencyResource('ticket', $ticket->getKey()),
        );
    }

    public function show(Ticket $ticket): TicketResource
    {
        Gate::authorize('view', $ticket);

        $ticket->loadMissing(['category', 'customer', 'assignee', 'attachments']);

        return new TicketResource($ticket);
    }
}

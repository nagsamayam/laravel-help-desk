<?php

declare(strict_types=1);

namespace App\Http\Controllers\API\V1;

use App\Domain\Ticket\Models\Ticket;
use App\Domain\Ticket\Routing\TicketRouter;
use App\Http\Controllers\Controller;
use App\Http\Requests\V1\RouteTicketRequest;
use App\Http\Resources\V1\TicketRoutingResource;
use App\Http\Responses\IdempotencyResponse;
use App\Infrastructure\Idempotency\IdempotencyResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Gate;

final class TicketRoutingController extends Controller
{
    public function route(
        RouteTicketRequest $request,
        Ticket $ticket,
    ): JsonResponse {
        Gate::authorize('route', $ticket);

        $persist = $request->boolean('persist', true);
        $router = TicketRouter::createDefault();

        $decision = $router->route($ticket, $persist);

        return new IdempotencyResponse(
            data: [
                'data' => (new TicketRoutingResource($decision))->resolve($request),
                'message' => "Ticket successfully evaluated and routed via rule [{$decision->matchedRule}].",
            ],
            status: Response::HTTP_OK,
            resource: new IdempotencyResource('ticket', $ticket->getKey()),
        );
    }
}

<?php

declare(strict_types=1);

namespace App\Http\Controllers\API\V1;

use App\Enums\Role;
use App\Http\Controllers\Controller;
use App\Http\Resources\V1\TicketStatusHistoryResource;
use App\Models\Ticket;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;

final class TicketStatusHistoryController extends Controller
{
    public function index(Request $request, Ticket $ticket): AnonymousResourceCollection
    {
        $user = $request->user();

        if ($user !== null && $user->hasRole(Role::Customer) && $ticket->customer_id !== $user->id) {
            abort(Response::HTTP_FORBIDDEN, 'You are not authorized to view status history for this ticket.');
        }

        $perPage = min((int) $request->input('per_page', 30), 100);
        $histories = $ticket->statusHistories()
            ->with('changedBy')
            ->latest('id')
            ->paginate($perPage);

        return TicketStatusHistoryResource::collection($histories);
    }
}

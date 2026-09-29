<?php

declare(strict_types=1);

namespace App\Http\Controllers\API\V1;

use App\Domain\Ticket\Models\Ticket;
use App\Http\Controllers\Controller;
use App\Http\Resources\V1\TicketStatusHistoryResource;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Gate;

final class TicketStatusHistoryController extends Controller
{
    public function index(Request $request, Ticket $ticket): AnonymousResourceCollection
    {
        Gate::authorize('viewStatusHistory', $ticket);

        $perPage = min((int) $request->input('per_page', 30), 100);
        $histories = $ticket->statusHistories()
            ->with('changedBy')
            ->latest('id')
            ->paginate($perPage);

        return TicketStatusHistoryResource::collection($histories);
    }
}

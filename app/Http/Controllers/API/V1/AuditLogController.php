<?php

declare(strict_types=1);

namespace App\Http\Controllers\API\V1;

use App\Enums\Role;
use App\Http\Controllers\Controller;
use App\Http\Requests\V1\FilterAuditLogsRequest;
use App\Http\Resources\V1\AuditLogResource;
use App\Models\AuditLog;
use App\Models\Ticket;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;

final class AuditLogController extends Controller
{
    public function index(FilterAuditLogsRequest $request): AnonymousResourceCollection
    {
        $query = AuditLog::query()->with('user');

        if ($request->filled('user_id')) {
            $query->where('user_id', (int) $request->input('user_id'));
        }

        if ($request->filled('action')) {
            $query->where('action', (string) $request->input('action'));
        }

        if ($request->filled('auditable_type')) {
            $query->where('auditable_type', (string) $request->input('auditable_type'));
        }

        if ($request->filled('auditable_id')) {
            $query->where('auditable_id', (string) $request->input('auditable_id'));
        }

        if ($request->filled('date_from')) {
            $query->whereDate('created_at', '>=', (string) $request->input('date_from'));
        }

        if ($request->filled('date_to')) {
            $query->whereDate('created_at', '<=', (string) $request->input('date_to'));
        }

        $perPage = min((int) $request->input('per_page', 30), 100);
        $logs = $query->latest('id')->paginate($perPage);

        return AuditLogResource::collection($logs);
    }

    public function ticketLogs(Request $request, Ticket $ticket): AnonymousResourceCollection
    {
        $user = $request->user();

        if ($user === null || $user->hasRole(Role::Customer)) {
            abort(Response::HTTP_FORBIDDEN, 'You are not authorized to view audit logs for this ticket.');
        }

        $perPage = min((int) $request->input('per_page', 30), 100);
        $logs = $ticket->auditLogs()
            ->with('user')
            ->latest('id')
            ->paginate($perPage);

        return AuditLogResource::collection($logs);
    }

    public function show(Request $request, AuditLog $auditLog): AuditLogResource
    {
        $user = $request->user();

        if ($user === null || $user->hasRole(Role::Customer)) {
            abort(Response::HTTP_FORBIDDEN, 'You are not authorized to view audit logs.');
        }

        $auditLog->loadMissing('user');

        return new AuditLogResource($auditLog);
    }
}

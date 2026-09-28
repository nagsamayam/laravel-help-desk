<?php

declare(strict_types=1);

use App\Enums\Role;
use App\Http\Controllers\API\V1\AuditLogController;
use App\Http\Controllers\API\V1\AuthController;
use App\Http\Controllers\API\V1\TicketAssignmentController;
use App\Http\Controllers\API\V1\TicketController;
use App\Http\Controllers\API\V1\TicketMessageController;
use App\Http\Controllers\API\V1\TicketRoutingController;
use App\Http\Controllers\API\V1\TicketStateController;
use App\Http\Controllers\API\V1\TicketStatusHistoryController;
use App\Http\Controllers\API\V1\UserController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->group(function () {
    Route::prefix('auth')->group(function () {
        Route::post('register', [AuthController::class, 'register'])
            ->middleware('throttle:auth-register');
        Route::post('login', [AuthController::class, 'login'])
            ->middleware('throttle:auth-login');
        Route::middleware('auth:api')->group(function () {
            Route::post('refresh', [AuthController::class, 'refresh'])
                ->middleware('throttle:auth-refresh');
            Route::get('me', [AuthController::class, 'me']);
            Route::post('logout', [AuthController::class, 'logout']);
        });
    });

    Route::middleware('auth:api')->group(function () {
        // Tickets listing & viewing
        Route::get('/tickets', [TicketController::class, 'index']);
        Route::get('/tickets/{ticket}', [TicketController::class, 'show']);

        // Ticket creation (Idempotent)
        Route::post('/tickets', [TicketController::class, 'store'])
            ->middleware('idempotent');

        // Ticket update & delete (Idempotent)
        Route::put('/tickets/{ticket}', [TicketController::class, 'update'])
            ->middleware('idempotent');
        Route::delete('/tickets/{ticket}', [TicketController::class, 'destroy'])
            ->middleware('idempotent');

        // Ticket state lifecycle transitions
        Route::post('/tickets/{ticket}/transition', [TicketStateController::class, 'transition'])
            ->middleware(['roles:'.Role::Admin->value.','.Role::Agent->value, 'idempotent']);
        Route::post('/tickets/{ticket}/resolve', [TicketStateController::class, 'resolve'])
            ->middleware(['roles:'.Role::Admin->value.','.Role::Agent->value, 'idempotent']);
        Route::post('/tickets/{ticket}/close', [TicketStateController::class, 'close'])
            ->middleware('idempotent');
        Route::post('/tickets/{ticket}/reopen', [TicketStateController::class, 'reopen'])
            ->middleware('idempotent');

        // Ticket assignment (Strategy & Assign Action)
        Route::post('/tickets/{ticket}/assign', [TicketAssignmentController::class, 'assign'])
            ->middleware(['roles:'.Role::Admin->value.','.Role::Agent->value, 'idempotent']);

        // Ticket routing (Chain of Responsibility)
        Route::post('/tickets/{ticket}/route', [TicketRoutingController::class, 'route'])
            ->middleware(['roles:'.Role::Admin->value.','.Role::Agent->value, 'idempotent']);

        // Ticket messages & conversations
        Route::get('/tickets/{ticket}/messages', [TicketMessageController::class, 'index']);
        Route::post('/tickets/{ticket}/messages', [TicketMessageController::class, 'store'])
            ->middleware('idempotent');

        // Ticket status history (domain lifecycle history)
        Route::get('/tickets/{ticket}/status-history', [TicketStatusHistoryController::class, 'index']);
        Route::get('/tickets/{ticket}/history', [TicketStatusHistoryController::class, 'index']);

        // Users & Agents
        Route::get('/users', [UserController::class, 'index'])
            ->middleware('roles:'.Role::Admin->value.','.Role::Agent->value);
        Route::get('/agents', [UserController::class, 'agents'])
            ->middleware('roles:'.Role::Admin->value.','.Role::Agent->value);

        // Ticket audit logs & global audit logs
        Route::get('/tickets/{ticket}/audit-logs', [AuditLogController::class, 'ticketLogs']);
        Route::get('/audit-logs', [AuditLogController::class, 'index'])
            ->middleware('roles:'.Role::Admin->value.','.Role::Agent->value);
        Route::get('/audit-logs/{auditLog}', [AuditLogController::class, 'show'])
            ->middleware('roles:'.Role::Admin->value.','.Role::Agent->value);
    });
});

<?php

declare(strict_types=1);

use App\Enums\Role;
use App\Http\Controllers\API\V1\AuthController;
use App\Http\Controllers\API\V1\TicketController;
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

    Route::prefix('tickets')
        ->middleware(['auth:api', 'roles:'.Role::Customer->value])
        ->group(function () {
            Route::get('/{ticket}', [TicketController::class, 'show']);
            Route::post('/', [TicketController::class, 'store']);
            Route::put('/{ticketId}', [TicketController::class, 'update']);
            Route::delete('/{ticketId}', [TicketController::class, 'destroy']);
        });

    Route::middleware([
        'auth:api',
        'roles:'.Role::Admin->value.','.Role::Agent->value,
    ])
        ->group(function () {
            Route::get('/tickets', [TicketController::class, 'store']);
        });
});

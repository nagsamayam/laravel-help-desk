<?php

declare(strict_types=1);

use App\Http\Controllers\API\V1\AuthController;
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
});

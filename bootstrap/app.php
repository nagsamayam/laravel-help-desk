<?php

use App\Http\Middleware\CheckRole;
use App\Http\Middleware\IdempotentRequest;
use App\Http\Responses\ApiResponse;
use App\Infrastructure\Idempotency\Exceptions\IdempotencyConflictException;
use App\Infrastructure\Idempotency\Exceptions\IdempotencyInFlightException;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withCommands([
        __DIR__.'/../app/Console/Commands',
    ])
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->alias([
            'roles' => CheckRole::class,
            'idempotent' => IdempotentRequest::class,
            'idempotency' => IdempotentRequest::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );
        $exceptions->render(function (Throwable $e, Request $request) {
            if (! ($request->is('api/*') || $request->expectsJson())) {
                return null;
            }

            if ($e instanceof HttpResponseException) {
                return $e->getResponse();
            }

            if ($e instanceof ValidationException) {
                return ApiResponse::error(
                    code: 'VALIDATION_ERROR',
                    message: $e->getMessage(),
                    details: $e->errors(),
                    status: Response::HTTP_UNPROCESSABLE_ENTITY,
                );
            }

            if ($e instanceof AuthenticationException) {
                return ApiResponse::error(
                    code: 'AUTH_UNAUTHORIZED',
                    message: $e->getMessage() ?: 'Unauthenticated.',
                    status: Response::HTTP_UNAUTHORIZED,
                );
            }

            if ($e instanceof AuthorizationException) {
                return ApiResponse::error(
                    code: 'FORBIDDEN',
                    message: $e->getMessage() ?: 'This action is unauthorized.',
                    status: Response::HTTP_FORBIDDEN,
                );
            }

            if ($e instanceof IdempotencyConflictException) {
                return ApiResponse::error(
                    code: 'IDEMPOTENCY_CONFLICT',
                    message: $e->getMessage() ?: 'A request with this idempotency key was already completed with a different request.',
                    status: Response::HTTP_CONFLICT,
                );
            }

            if ($e instanceof IdempotencyInFlightException) {
                return ApiResponse::error(
                    code: 'IDEMPOTENCY_IN_FLIGHT',
                    message: $e->getMessage() ?: 'A request with this idempotency key is currently in progress.',
                    status: Response::HTTP_CONFLICT,
                );
            }

            $statusCode = $e instanceof HttpExceptionInterface
                ? $e->getStatusCode()
                : Response::HTTP_INTERNAL_SERVER_ERROR;

            $errorCode = match ($statusCode) {
                Response::HTTP_BAD_REQUEST => 'BAD_REQUEST',
                Response::HTTP_UNAUTHORIZED => 'UNAUTHENTICATED',
                Response::HTTP_FORBIDDEN => 'UNAUTHORIZED',
                Response::HTTP_NOT_FOUND => 'NOT_FOUND',
                Response::HTTP_CONFLICT => 'CONFLICT',
                Response::HTTP_UNPROCESSABLE_ENTITY => 'UNPROCESSABLE_ENTITY',
                Response::HTTP_TOO_MANY_REQUESTS => 'RATE_LIMITED',
                Response::HTTP_SERVICE_UNAVAILABLE => 'SERVICE_UNAVAILABLE',
                default => 'INTERNAL_ERROR',
            };

            $message = ($statusCode === Response::HTTP_INTERNAL_SERVER_ERROR && ! config('app.debug'))
                ? 'An unexpected error occurred.'
                : $e->getMessage();

            return ApiResponse::error(
                code: $errorCode,
                message: $message ?: 'Server error',
                status: $statusCode,
            );
        });
    })->create();

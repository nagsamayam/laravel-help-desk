<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Infrastructure\Monitoring\Prometheus\PrometheusMetrics;
use Closure;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Throwable;

final class RecordHttpMetrics
{
    public function __construct(
        private readonly PrometheusMetrics $metrics,
    ) {}

    public function handle(Request $request, Closure $next): mixed
    {
        if ($request->is('metrics')) {
            return $next($request);
        }

        $startedAt = hrtime(true);
        $method = strtoupper($request->method());
        $route = $this->routeName($request);

        $this->metrics->incrementInFlight($method, $route);

        try {
            $response = $next($request);
            $statusCode = $response->getStatusCode();

            return $response;
        } catch (Throwable $exception) {
            $statusCode = $this->statusCodeForException($exception);
            throw $exception;
        } finally {
            $durationSeconds = (hrtime(true) - $startedAt) / 1_000_000_000;

            try {
                $this->metrics->recordHttpRequest(
                    method: $method,
                    route: $route,
                    statusCode: $statusCode ?? 500,
                    durationSeconds: $durationSeconds,
                );
            } finally {
                $this->metrics->decrementInFlight($method, $route);
            }
        }
    }

    private function routeName(Request $request): string
    {
        $route = $request->route();

        if ($route !== null) {
            $uri = $route->uri();

            if ($uri !== '') {
                return $uri;
            }
        }

        // Avoid using the raw URL as a metric label because IDs/query strings
        // can create unbounded Prometheus cardinality.
        return 'unmatched';
    }

    private function statusCodeForException(Throwable $exception): int
    {
        if ($exception instanceof HttpResponseException) {
            return $exception->getResponse()->getStatusCode();
        }

        if ($exception instanceof ValidationException) {
            return 422;
        }

        if ($exception instanceof AuthenticationException) {
            return 401;
        }

        if ($exception instanceof AuthorizationException) {
            return 403;
        }

        if ($exception instanceof HttpExceptionInterface) {
            return $exception->getStatusCode();
        }

        return 500;
    }
}

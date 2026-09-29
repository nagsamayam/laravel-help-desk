<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Infrastructure\Idempotency\IdempotencyManager;
use App\Infrastructure\Idempotency\IdempotencyResult;
use Closure;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use RuntimeException;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;

final class IdempotentRequest
{
    public function __construct(
        private readonly IdempotencyManager $idempotencyManager,
    ) {}

    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request): (SymfonyResponse)  $next
     */
    public function handle(
        Request $request,
        Closure $next,
        ?string $scopeType = 'user',
        ?string $operation = null,
        bool|string $enforce = false,
    ): SymfonyResponse {
        if ($request->isMethodSafe()) {
            return $next($request);
        }

        $key = trim((string) $request->header('Idempotency-Key', ''));

        if ($key === '') {
            $isRequired = $enforce === true || $enforce === 'required' || $enforce === 'true';

            if ($isRequired) {
                throw ValidationException::withMessages([
                    'Idempotency-Key' => 'The Idempotency-Key header is required.',
                ]);
            }

            return $next($request);
        }

        if (strlen($key) < 16 || strlen($key) > 255) {
            throw ValidationException::withMessages([
                'Idempotency-Key' => 'The Idempotency-Key header must be between 16 and 255 characters.',
            ]);
        }

        if (! preg_match('/^[\x21-\x7E]+$/', $key)) {
            throw ValidationException::withMessages([
                'Idempotency-Key' => 'The Idempotency-Key header contains invalid characters.',
            ]);
        }

        $resolvedScopeType = $scopeType ?: 'user';
        $resolvedScopeId = $this->resolveScopeId($request);
        $resolvedOperation = $this->resolveOperation($request, $operation);
        $payload = $this->resolvePayload($request);

        /** @var SymfonyResponse|null $capturedResponse */
        $capturedResponse = null;

        $result = $this->idempotencyManager->execute(
            scopeType: $resolvedScopeType,
            scopeId: $resolvedScopeId,
            operation: $resolvedOperation,
            key: $key,
            requestPayload: $payload,
            callback: function () use ($request, $next, &$capturedResponse): IdempotencyResult {
                $capturedResponse = $next($request);

                if ($capturedResponse->getStatusCode() >= SymfonyResponse::HTTP_INTERNAL_SERVER_ERROR) {
                    throw new RuntimeException(
                        'Server error during request processing: '.$capturedResponse->getStatusCode(),
                    );
                }

                $body = $this->extractResponseBody($capturedResponse);
                $headers = $this->extractResponseHeaders($capturedResponse);

                return new IdempotencyResult(
                    status: $capturedResponse->getStatusCode(),
                    body: $body,
                    replayed: false,
                    headers: $headers,
                );
            },
        );

        if ($result->replayed) {
            $response = new JsonResponse($result->body, $result->status);

            foreach ($result->headers as $headerName => $headerValue) {
                $response->headers->set($headerName, $headerValue);
            }

            $response->headers->set('Idempotency-Replayed', 'true');

            return $response;
        }

        if ($capturedResponse !== null) {
            $capturedResponse->headers->set('Idempotency-Replayed', 'false');

            return $capturedResponse;
        }

        $response = new JsonResponse($result->body, $result->status);
        $response->headers->set('Idempotency-Replayed', 'false');

        return $response;
    }

    private function resolveScopeId(Request $request): string
    {
        $user = $request->user();

        if ($user !== null) {
            return (string) $user->getKey();
        }

        return $request->ip() ?: 'anonymous';
    }

    private function resolveOperation(Request $request, ?string $operation): string
    {
        if ($operation !== null && trim($operation) !== '') {
            return trim($operation);
        }

        $routeName = $request->route()?->getName();
        if ($routeName !== null && $routeName !== '') {
            return $routeName;
        }

        $action = $request->route()?->getActionName();
        if ($action !== null && $action !== 'Closure' && $action !== '') {
            $shortAction = class_basename($action);
            $sanitized = preg_replace('/[^a-zA-Z0-9_.:-]+/', '.', $shortAction);

            return substr((string) $sanitized, 0, 100);
        }

        $fallback = $request->method().':'.($request->route()?->uri() ?? $request->path());
        $sanitizedFallback = preg_replace('/[^a-zA-Z0-9_.:-]+/', '.', $fallback);

        return substr((string) $sanitizedFallback, 0, 100);
    }

    private function resolvePayload(Request $request): array
    {
        $routeParameters = $request->route()?->parameters() ?? [];

        return array_merge($routeParameters, $request->all());
    }

    private function extractResponseBody(SymfonyResponse $response): array
    {
        if ($response instanceof JsonResponse) {
            $data = $response->getData(true);

            return is_array($data) ? $data : ['data' => $data];
        }

        $content = (string) $response->getContent();
        $decoded = json_decode($content, true);

        if (is_array($decoded)) {
            return $decoded;
        }

        return $content !== '' ? ['content' => $content] : [];
    }

    private function extractResponseHeaders(SymfonyResponse $response): array
    {
        $headers = [];
        $ignored = [
            'set-cookie',
            'x-powered-by',
            'date',
            'idempotency-replayed',
            'content-type',
            'content-length',
        ];

        foreach ($response->headers->all() as $name => $values) {
            if (in_array(strtolower($name), $ignored, true)) {
                continue;
            }

            $headers[$name] = implode(', ', $values);
        }

        return $headers;
    }
}

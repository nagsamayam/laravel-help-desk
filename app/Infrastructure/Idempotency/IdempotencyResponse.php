<?php

declare(strict_types=1);

namespace App\Infrastructure\Idempotency;

use Illuminate\Http\JsonResponse;

final class IdempotencyResponse extends JsonResponse
{
    public function __construct(
        array $data,
        int $status,
        IdempotencyResource $resource,
    ) {
        parent::__construct($data, $status);

        $this->resource = $resource;
    }

    private IdempotencyResource $resource;

    public function idempotencyResource(): IdempotencyResource
    {
        return $this->resource;
    }
}

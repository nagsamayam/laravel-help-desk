<?php

declare(strict_types=1);

namespace App\Http\Responses;

use App\Infrastructure\Idempotency\IdempotencyResource;
use Illuminate\Http\JsonResponse;

final class IdempotencyResponse extends JsonResponse
{
    private IdempotencyResource $resource;

    public function __construct(
        array $data,
        int $status,
        IdempotencyResource $resource,
    ) {
        parent::__construct($data, $status);

        $this->resource = $resource;
    }

    public function idempotencyResource(): IdempotencyResource
    {
        return $this->resource;
    }
}

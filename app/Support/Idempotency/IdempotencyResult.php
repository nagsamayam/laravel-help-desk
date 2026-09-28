<?php

declare(strict_types=1);

namespace App\Support\Idempotency;

final readonly class IdempotencyResult
{
    public function __construct(
        public int $status,
        public array $body,
        public bool $replayed,
        public ?string $resourceType = null,
        public string|int|null $resourceId = null,
        public array $headers = [],
    ) {}
}

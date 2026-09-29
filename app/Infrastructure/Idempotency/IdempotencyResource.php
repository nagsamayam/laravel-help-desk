<?php

declare(strict_types=1);

namespace App\Infrastructure\Idempotency;

use InvalidArgumentException;

final readonly class IdempotencyResource
{
    public function __construct(
        public string $type,
        public string|int $id,
    ) {
        if (
            $type === ''
            || strlen($type) > 100
            || preg_match('/^[A-Za-z0-9_.:-]+$/', $type) !== 1
        ) {
            throw new InvalidArgumentException('The idempotency resource type is invalid.');
        }

        if ((string) $id === '') {
            throw new InvalidArgumentException('The idempotency resource id is required.');
        }
    }
}

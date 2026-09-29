<?php

declare(strict_types=1);

namespace App\Infrastructure\Idempotency\Exceptions;

use RuntimeException;

final class IdempotencyConflictException extends RuntimeException
{
    public function __construct(string $message = 'The Idempotency-Key was already used with a different request.')
    {
        parent::__construct($message);
    }
}

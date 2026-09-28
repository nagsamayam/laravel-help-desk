<?php

declare(strict_types=1);

namespace App\Exceptions;

use RuntimeException;
use Throwable;

final class IdempotencyInFlightException extends RuntimeException
{
    public function __construct(
        string $message = 'A request with this idempotency key is currently in progress.',
        int $code = 0,
        ?Throwable $previous = null,
    ) {
        parent::__construct($message, $code, $previous);
    }
}

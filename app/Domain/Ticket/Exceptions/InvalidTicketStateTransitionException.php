<?php

declare(strict_types=1);

namespace App\Domain\Ticket\Exceptions;

use App\Domain\Ticket\Enums\TicketStatus;
use DomainException;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;

final class InvalidTicketStateTransitionException extends DomainException implements HttpExceptionInterface
{
    public function __construct(
        public readonly TicketStatus $from,
        public readonly TicketStatus $to,
        string $message = '',
    ) {
        $formattedMessage = $message !== '' ? $message : sprintf(
            'Cannot transition ticket from status [%s] to [%s].',
            $from->value,
            $to->value
        );

        parent::__construct($formattedMessage);
    }

    public function getStatusCode(): int
    {
        return 422;
    }

    public function getHeaders(): array
    {
        return [];
    }
}

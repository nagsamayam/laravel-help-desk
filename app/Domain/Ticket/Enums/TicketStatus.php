<?php

declare(strict_types=1);

namespace App\Domain\Ticket\Enums;

enum TicketStatus: string
{
    case Open = 'OPEN';
    case InProgess = 'IN_PROGRESS';
    case WaitingForCustomer = 'WAITING_FOR_CUSTOMER';
    case Resolved = 'RESOLVED';
    case Closed = 'CLOSED';
}

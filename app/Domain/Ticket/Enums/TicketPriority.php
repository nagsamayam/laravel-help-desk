<?php

declare(strict_types=1);

namespace App\Domain\Ticket\Enums;

enum TicketPriority: string
{
    case Low = 'LOW';
    case Medium = 'MEDIUM';
    case High = 'HIGH';
    case Urgent = 'URGENT';
}

<?php

declare(strict_types=1);

namespace App\Domain\Ticket\Actions;

use App\Domain\Ticket\Models\Ticket;

final class DeleteTicketAction
{
    public function execute(Ticket $ticket): void
    {
        $ticket->delete();
    }
}

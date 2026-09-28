<?php

declare(strict_types=1);

namespace App\Actions;

use App\Models\Ticket;

final class DeleteTicketAction
{
    public function execute(Ticket $ticket): void
    {
        $ticket->delete();
    }
}

<?php

declare(strict_types=1);

namespace App\Actions;

use App\Models\Ticket;

final class UpdateTicketAction
{
    public function execute(Ticket $ticket, array $attributes): Ticket
    {
        $ticket->update($attributes);

        return $ticket->refresh();
    }
}

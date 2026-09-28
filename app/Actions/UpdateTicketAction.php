<?php

declare(strict_types=1);

namespace App\Actions;

use App\DTOs\UpdateTicketData;
use App\Models\Ticket;

final class UpdateTicketAction
{
    public function execute(Ticket $ticket, UpdateTicketData|array $attributes): Ticket
    {
        $data = $attributes instanceof UpdateTicketData
            ? [
                'subject' => $attributes->subject,
                'description' => $attributes->description,
                'category_id' => $attributes->category_id,
                'priority' => $attributes->priority,
            ]
            : $attributes;

        $ticket->update($data);

        return $ticket->refresh();
    }
}

<?php

declare(strict_types=1);

namespace App\Actions;

use App\DTOs\CreateTicketData;
use App\Models\Ticket;

final class CreateTicketAction
{
    public function execute(
        CreateTicketData $createTicketData,
    ): Ticket {
        return Ticket::query()->create([
            'customer_id' => $createTicketData->customer_id,
            'category_id' => $createTicketData->category_id,
            'subject' => $createTicketData->subject,
            'description' => $createTicketData->description,
            'priority' => $createTicketData->priority,
        ]);
    }
}

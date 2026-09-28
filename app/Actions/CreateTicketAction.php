<?php

declare(strict_types=1);

namespace App\Actions;

use App\DTOs\CreateTicketData;
use App\Enums\TicketStatus;
use App\Events\Tickets\TicketCreated;
use App\Models\Ticket;

final class CreateTicketAction
{
    public function execute(
        CreateTicketData $createTicketData,
    ): Ticket {
        $ticket = Ticket::query()->create([
            'customer_id' => $createTicketData->customer_id,
            'category_id' => $createTicketData->category_id,
            'subject' => $createTicketData->subject,
            'description' => $createTicketData->description,
            'priority' => $createTicketData->priority,
            'status' => TicketStatus::Open,
        ]);

        TicketCreated::dispatch($ticket);

        return $ticket;
    }
}

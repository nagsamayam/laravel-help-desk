<?php

declare(strict_types=1);

namespace App\Domain\Ticket\Actions;

use App\Domain\Ticket\DTOs\CreateTicketData;
use App\Domain\Ticket\Enums\TicketStatus;
use App\Domain\Ticket\Events\TicketCreated;
use App\Domain\Ticket\Models\Ticket;

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

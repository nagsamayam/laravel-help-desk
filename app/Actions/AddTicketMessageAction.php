<?php

declare(strict_types=1);

namespace App\Actions;

use App\Events\Tickets\TicketMessageAdded;
use App\Models\Ticket;
use App\Models\TicketMessage;
use App\Models\User;

final class AddTicketMessageAction
{
    public function execute(
        Ticket $ticket,
        User|int $user,
        string $message,
        bool $isInternal = false,
    ): TicketMessage {
        $userId = $user instanceof User ? $user->id : (int) $user;

        $ticketMessage = $ticket->messages()->create([
            'user_id' => $userId,
            'message' => $message,
            'is_internal' => $isInternal,
        ]);

        TicketMessageAdded::dispatch($ticket, $ticketMessage);

        return $ticketMessage;
    }
}

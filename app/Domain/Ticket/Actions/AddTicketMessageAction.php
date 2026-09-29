<?php

declare(strict_types=1);

namespace App\Domain\Ticket\Actions;

use App\Domain\Identity\Models\User;
use App\Domain\Ticket\Events\TicketMessageAdded;
use App\Domain\Ticket\Models\Ticket;
use App\Domain\Ticket\Models\TicketMessage;

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

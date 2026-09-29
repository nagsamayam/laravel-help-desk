<?php

declare(strict_types=1);

namespace App\Domain\Ticket\Actions;

use App\Domain\Identity\Models\User;
use App\Domain\Ticket\Events\TicketAssigned;
use App\Domain\Ticket\Models\Ticket;

final class AssignTicketAction
{
    public function execute(Ticket $ticket, User|int|null $agent): Ticket
    {
        $previousAgentId = $ticket->assigned_to !== null ? (int) $ticket->assigned_to : null;
        $agentModel = $agent instanceof User ? $agent : ($agent !== null ? User::query()->find($agent) : null);
        $agentId = $agentModel?->id ?? ($agent !== null ? (int) $agent : null);

        $ticket->assigned_to = $agentId;
        $ticket->save();

        $freshTicket = $ticket->refresh();

        TicketAssigned::dispatch($freshTicket, $agentModel, $previousAgentId);

        return $freshTicket;
    }
}

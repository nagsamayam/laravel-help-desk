<?php

declare(strict_types=1);

namespace App\Domain\Ticket\Events;

use App\Domain\Identity\Models\User;
use App\Domain\Ticket\Models\Ticket;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

final class TicketAssigned implements ShouldDispatchAfterCommit
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public readonly Ticket $ticket,
        public readonly ?User $agent,
        public readonly ?int $previousAgentId = null,
    ) {}
}

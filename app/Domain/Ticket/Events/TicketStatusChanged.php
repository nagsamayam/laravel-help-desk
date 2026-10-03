<?php

declare(strict_types=1);

namespace App\Domain\Ticket\Events;

use App\Domain\Ticket\Enums\TicketStatus;
use App\Domain\Ticket\Models\Ticket;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PresenceChannel;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

final class TicketStatusChanged implements ShouldBroadcast, ShouldDispatchAfterCommit
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public readonly Ticket $ticket,
        public readonly TicketStatus $previousStatus,
        public readonly TicketStatus $newStatus,
    ) {}

    /**
     * @return array<int, Channel>
     */
    public function broadcastOn(): array
    {
        return [
            new PresenceChannel('tickets.'.$this->ticket->id),
            new PrivateChannel('agent.feed'),
            new PrivateChannel('users.'.$this->ticket->customer_id),
        ];
    }

    public function broadcastAs(): string
    {
        return 'ticket.status.changed';
    }

    /**
     * @return array<string, mixed>
     */
    public function broadcastWith(): array
    {
        return [
            'ticket_id' => $this->ticket->id,
            'previous_status' => $this->previousStatus->value,
            'new_status' => $this->newStatus->value,
            'status' => $this->newStatus->value,
            'updated_at' => $this->ticket->updated_at?->toIso8601String(),
        ];
    }
}

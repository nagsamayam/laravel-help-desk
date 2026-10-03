<?php

declare(strict_types=1);

namespace App\Domain\Ticket\Events;

use App\Domain\Ticket\Models\Ticket;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

final class TicketCreated implements ShouldBroadcast, ShouldDispatchAfterCommit
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public readonly Ticket $ticket,
    ) {}

    /**
     * @return array<int, Channel>
     */
    public function broadcastOn(): array
    {
        return [
            new PrivateChannel('agent.feed'),
            new PrivateChannel('users.'.$this->ticket->customer_id),
        ];
    }

    public function broadcastAs(): string
    {
        return 'ticket.created';
    }

    /**
     * @return array<string, mixed>
     */
    public function broadcastWith(): array
    {
        return [
            'ticket' => [
                'id' => $this->ticket->id,
                'subject' => $this->ticket->subject,
                'status' => $this->ticket->status->value,
                'priority' => $this->ticket->priority->value,
                'category_id' => $this->ticket->category_id,
                'customer_id' => $this->ticket->customer_id,
                'assigned_to' => $this->ticket->assigned_to,
                'created_at' => $this->ticket->created_at?->toIso8601String(),
            ],
        ];
    }
}

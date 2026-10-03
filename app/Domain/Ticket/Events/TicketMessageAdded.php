<?php

declare(strict_types=1);

namespace App\Domain\Ticket\Events;

use App\Domain\Ticket\Models\Ticket;
use App\Domain\Ticket\Models\TicketMessage;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PresenceChannel;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

final class TicketMessageAdded implements ShouldBroadcast, ShouldDispatchAfterCommit
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public readonly Ticket $ticket,
        public readonly TicketMessage $message,
    ) {}

    /**
     * @return array<int, Channel>
     */
    public function broadcastOn(): array
    {
        if ($this->message->is_internal) {
            return [
                new PrivateChannel('tickets.'.$this->ticket->id.'.internal'),
                new PrivateChannel('agent.feed'),
            ];
        }

        return [
            new PresenceChannel('tickets.'.$this->ticket->id),
        ];
    }

    public function broadcastAs(): string
    {
        return 'ticket.message.added';
    }

    /**
     * @return array<string, mixed>
     */
    public function broadcastWith(): array
    {
        return [
            'ticket_id' => $this->ticket->id,
            'message' => [
                'id' => $this->message->id,
                'ticket_id' => $this->message->ticket_id,
                'user_id' => $this->message->user_id,
                'message' => $this->message->message,
                'is_internal' => (bool) $this->message->is_internal,
                'created_at' => $this->message->created_at?->toIso8601String(),
                'user' => [
                    'id' => $this->message->user?->id,
                    'name' => $this->message->user?->name,
                    'role' => $this->message->user?->role?->value,
                ],
            ],
        ];
    }
}

<?php

declare(strict_types=1);

namespace App\Domain\Ticket\Events;

use App\Domain\Identity\Models\User;
use App\Domain\Ticket\Models\Ticket;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PresenceChannel;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

final class TicketAssigned implements ShouldBroadcast, ShouldDispatchAfterCommit
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public readonly Ticket $ticket,
        public readonly ?User $agent,
        public readonly ?int $previousAgentId = null,
    ) {}

    /**
     * @return array<int, Channel>
     */
    public function broadcastOn(): array
    {
        $channels = [
            new PresenceChannel('tickets.'.$this->ticket->id),
            new PrivateChannel('agent.feed'),
        ];

        if ($this->agent) {
            $channels[] = new PrivateChannel('users.'.$this->agent->id);
        }

        if ($this->previousAgentId) {
            $channels[] = new PrivateChannel('users.'.$this->previousAgentId);
        }

        return $channels;
    }

    public function broadcastAs(): string
    {
        return 'ticket.assigned';
    }

    /**
     * @return array<string, mixed>
     */
    public function broadcastWith(): array
    {
        return [
            'ticket_id' => $this->ticket->id,
            'assigned_to' => $this->agent?->id,
            'assigned_to_name' => $this->agent?->name,
            'previous_agent_id' => $this->previousAgentId,
        ];
    }
}

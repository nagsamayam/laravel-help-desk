<?php

namespace App\Console\Commands;

use App\Domain\Ticket\Enums\TicketStatus;
use App\Domain\Ticket\Events\TicketCreated;
use App\Domain\Ticket\Events\TicketMessageAdded;
use App\Domain\Ticket\Events\TicketStatusChanged;
use App\Domain\Ticket\Models\Ticket;
use App\Domain\Ticket\Models\TicketMessage;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('app:test-reverb-command')]
#[Description('Command description')]
class TestReverbCommand extends Command
{
    /**
     * Execute the console command.
     */
    public function handle()
    {
        $ticket = Ticket::with(['customer', 'assignee'])->first();
        event(new TicketCreated($ticket));
        event(new TicketStatusChanged(
            $ticket,
            TicketStatus::InProgess,
            TicketStatus::Open
        ));

        $message = TicketMessage::factory()->create([
            'ticket_id' => $ticket->id,
            'user_id' => $ticket->customer_id,
            'message' => 'Testing Reverb WebSocket message!',
            'is_internal' => false,
        ]);
        event(new TicketMessageAdded($ticket, $message));
    }
}

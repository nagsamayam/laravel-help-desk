<?php

declare(strict_types=1);

namespace App\Observers;

use App\Enums\TicketStatus;
use App\Models\Ticket;
use Illuminate\Support\Facades\Log;

final class TicketObserver
{
    public function created(Ticket $ticket): void
    {
        $statusValue = $ticket->status instanceof TicketStatus
            ? $ticket->status->value
            : ($ticket->status ?? 'open');

        Log::info('Ticket model created', [
            'ticket_id' => $ticket->id,
            'customer_id' => $ticket->customer_id,
            'status' => $statusValue,
        ]);
    }

    public function updated(Ticket $ticket): void
    {
        if ($ticket->wasChanged('status')) {
            $rawOriginal = $ticket->getRawOriginal('status');
            $previousStatus = is_string($rawOriginal)
                ? TicketStatus::tryFrom($rawOriginal) ?? $ticket->status
                : ($rawOriginal instanceof TicketStatus ? $rawOriginal : $ticket->status);

            $fromValue = $previousStatus instanceof TicketStatus ? $previousStatus->value : ($previousStatus ?? 'open');
            $toValue = $ticket->status instanceof TicketStatus ? $ticket->status->value : ($ticket->status ?? 'open');

            Log::info('Ticket status changed via model update', [
                'ticket_id' => $ticket->id,
                'from' => $fromValue,
                'to' => $toValue,
            ]);
        }

        if ($ticket->wasChanged('assigned_to')) {
            Log::info('Ticket assigned_to changed via model update', [
                'ticket_id' => $ticket->id,
                'assigned_to' => $ticket->assigned_to,
            ]);
        }
    }

    public function deleted(Ticket $ticket): void
    {
        Log::info('Ticket model deleted', [
            'ticket_id' => $ticket->id,
        ]);
    }
}

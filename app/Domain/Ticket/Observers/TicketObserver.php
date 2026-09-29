<?php

declare(strict_types=1);

namespace App\Domain\Ticket\Observers;

use App\Domain\Audit\Services\AuditLogger;
use App\Domain\Ticket\Enums\TicketStatus;
use App\Domain\Ticket\Models\Ticket;
use App\Domain\Ticket\Models\TicketStatusHistory;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

final class TicketObserver
{
    public function __construct(
        private readonly AuditLogger $auditLogger = new AuditLogger,
    ) {}

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

        TicketStatusHistory::create([
            'ticket_id' => $ticket->id,
            'from_status' => null,
            'to_status' => $ticket->status instanceof TicketStatus ? $ticket->status : TicketStatus::from((string) $ticket->status),
            'changed_by' => Auth::id() ?? $ticket->customer_id,
            'reason' => 'Ticket created',
            'created_at' => now(),
        ]);

        $this->auditLogger->log(
            action: 'created',
            auditable: $ticket,
            newValues: $ticket->attributesToArray(),
        );
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

            $reason = app()->bound('request') ? request()?->input('reason') : null;

            TicketStatusHistory::create([
                'ticket_id' => $ticket->id,
                'from_status' => $previousStatus instanceof TicketStatus ? $previousStatus : TicketStatus::tryFrom((string) $previousStatus),
                'to_status' => $ticket->status instanceof TicketStatus ? $ticket->status : TicketStatus::from((string) $ticket->status),
                'changed_by' => Auth::id(),
                'reason' => is_string($reason) ? $reason : 'Status changed',
                'created_at' => now(),
            ]);
        }

        if ($ticket->wasChanged('assigned_to')) {
            Log::info('Ticket assigned_to changed via model update', [
                'ticket_id' => $ticket->id,
                'assigned_to' => $ticket->assigned_to,
            ]);
        }

        $changes = $ticket->getChanges();
        $original = [];
        foreach (array_keys($changes) as $key) {
            $original[$key] = $ticket->getRawOriginal($key);
        }

        $this->auditLogger->log(
            action: 'updated',
            auditable: $ticket,
            oldValues: $original,
            newValues: $changes,
        );
    }

    public function deleted(Ticket $ticket): void
    {
        Log::info('Ticket model deleted', [
            'ticket_id' => $ticket->id,
        ]);

        $this->auditLogger->log(
            action: 'deleted',
            auditable: $ticket,
            oldValues: $ticket->attributesToArray(),
        );
    }
}

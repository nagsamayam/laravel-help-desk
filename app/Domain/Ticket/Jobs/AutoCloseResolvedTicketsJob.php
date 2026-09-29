<?php

declare(strict_types=1);

namespace App\Domain\Ticket\Jobs;

use App\Domain\Ticket\Actions\CloseTicketAction;
use App\Domain\Ticket\Enums\TicketStatus;
use App\Domain\Ticket\Models\Ticket;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Log;
use Throwable;

final class AutoCloseResolvedTicketsJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    /**
     * @var array<int, int>
     */
    public array $backoff = [30, 60, 120];

    public int $timeout = 180;

    public function __construct(
        public readonly int $daysAfterResolved = 3,
    ) {
        $this->onQueue('maintenance');
    }

    /**
     * Execute the job.
     */
    public function handle(CloseTicketAction $closeTicketAction): int
    {
        $threshold = Carbon::now()->subDays($this->daysAfterResolved);
        $closedCount = 0;

        Ticket::query()
            ->where('status', TicketStatus::Resolved)
            ->where('updated_at', '<', $threshold)
            ->chunkById(100, function ($tickets) use ($closeTicketAction, &$closedCount) {
                foreach ($tickets as $ticket) {
                    $closeTicketAction->execute($ticket);
                    $closedCount++;

                    Log::info('Resolved ticket automatically closed due to inactivity threshold', [
                        'ticket_id' => $ticket->id,
                        'resolved_since' => $ticket->updated_at?->toIso8601String(),
                        'days_threshold' => $this->daysAfterResolved,
                    ]);
                }
            });

        Log::info('Completed auto-close resolved tickets job', [
            'days_threshold' => $this->daysAfterResolved,
            'closed_count' => $closedCount,
        ]);

        return $closedCount;
    }

    /**
     * Handle job failure.
     */
    public function failed(?Throwable $exception): void
    {
        Log::error('AutoCloseResolvedTicketsJob failed to execute', [
            'exception' => $exception?->getMessage(),
            'trace' => $exception?->getTraceAsString(),
        ]);
    }
}

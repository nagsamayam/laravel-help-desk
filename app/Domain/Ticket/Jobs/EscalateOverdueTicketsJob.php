<?php

declare(strict_types=1);

namespace App\Domain\Ticket\Jobs;

use App\Domain\Ticket\Enums\TicketPriority;
use App\Domain\Ticket\Enums\TicketStatus;
use App\Domain\Ticket\Models\Ticket;
use App\Domain\Ticket\Specifications\OverdueTicketSpecification;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\Middleware\WithoutOverlapping;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Log;
use Throwable;

final class EscalateOverdueTicketsJob implements ShouldBeUnique, ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    /**
     * @var array<int, int>
     */
    public array $backoff = [30, 60, 120];

    public int $timeout = 180;

    public int $uniqueFor = 1800;

    public function __construct(
        public readonly ?int $hoursOverdue = 24,
    ) {
        $this->onQueue('maintenance');
    }

    /**
     * The unique ID of the job.
     */
    public function uniqueId(): string
    {
        return 'escalate-overdue-tickets-'.($this->hoursOverdue ?? 24);
    }

    /**
     * Get the middleware the job should pass through.
     *
     * @return array<int, object>
     */
    public function middleware(): array
    {
        return [
            (new WithoutOverlapping($this->uniqueId()))->expireAfter(180),
        ];
    }

    /**
     * Execute the job.
     */
    public function handle(): int
    {
        $threshold = Carbon::now()->subHours($this->hoursOverdue ?? 24);
        $specification = new OverdueTicketSpecification($threshold);

        $escalatedCount = 0;

        Ticket::matching($specification)
            ->where('priority', '!=', TicketPriority::Urgent)
            ->whereNotIn('status', [TicketStatus::Resolved, TicketStatus::Closed])
            ->chunkById(100, function ($tickets) use (&$escalatedCount) {
                foreach ($tickets as $ticket) {
                    $originalPriority = $ticket->priority;

                    $ticket->update([
                        'priority' => TicketPriority::Urgent,
                    ]);

                    $escalatedCount++;

                    Log::warning('Overdue ticket automatically escalated to urgent priority', [
                        'ticket_id' => $ticket->id,
                        'previous_priority' => $originalPriority->value,
                        'created_at' => $ticket->created_at?->toIso8601String(),
                    ]);
                }
            });

        Log::info('Completed overdue tickets escalation job', [
            'hours_threshold' => $this->hoursOverdue,
            'escalated_count' => $escalatedCount,
        ]);

        return $escalatedCount;
    }

    /**
     * Handle job failure.
     */
    public function failed(?Throwable $exception): void
    {
        Log::error('EscalateOverdueTicketsJob failed to execute', [
            'exception' => $exception?->getMessage(),
            'trace' => $exception?->getTraceAsString(),
        ]);
    }
}

<?php

declare(strict_types=1);

namespace App\Domain\Ticket\Jobs;

use App\Domain\Ticket\Models\Ticket;
use App\Domain\Ticket\Routing\Rules\TicketRoutingDecision;
use App\Domain\Ticket\Routing\TicketRouter;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Throwable;

final class ProcessTicketRoutingJob implements ShouldBeUnique, ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    /**
     * @var array<int, int>
     */
    public array $backoff = [10, 30, 60];

    public int $timeout = 60;

    public int $uniqueFor = 300;

    public function __construct(
        public readonly Ticket $ticket,
        public readonly bool $persist = true,
    ) {
        $this->onQueue('routing');
    }

    /**
     * The unique ID of the job.
     */
    public function uniqueId(): string
    {
        return (string) $this->ticket->id;
    }

    /**
     * Execute the job.
     */
    public function handle(TicketRouter $router): TicketRoutingDecision
    {
        $this->ticket->refresh();

        $decision = $router->route($this->ticket, persist: $this->persist);

        Log::info('Ticket asynchronously routed via background job', [
            'ticket_id' => $this->ticket->id,
            'rule_applied' => $decision->matchedRule,
            'assigned_to' => $decision->assignedTo,
            'priority' => $decision->priority->value,
            'reason' => $decision->reason,
            'persisted' => $this->persist,
        ]);

        return $decision;
    }

    /**
     * Handle job failure.
     */
    public function failed(?Throwable $exception): void
    {
        Log::error('ProcessTicketRoutingJob failed for ticket', [
            'ticket_id' => $this->ticket->id,
            'exception' => $exception?->getMessage(),
        ]);
    }
}

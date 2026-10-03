<?php

declare(strict_types=1);

use App\Domain\Ticket\Actions\CloseTicketAction;
use App\Domain\Ticket\Jobs\AutoCloseResolvedTicketsJob;
use App\Domain\Ticket\Jobs\EscalateOverdueTicketsJob;
use App\Infrastructure\Idempotency\Jobs\PruneExpiredIdempotencyKeysJob;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Artisan::command('tickets:escalate-overdue {--hours=24 : Number of hours threshold}', function () {
    $hours = (int) $this->option('hours');
    $count = (new EscalateOverdueTicketsJob($hours))->handle();
    $this->info("Escalated {$count} overdue ticket(s) to urgent priority.");
})->purpose('Escalate overdue tickets to urgent priority');

Artisan::command('tickets:auto-close-resolved {--days=3 : Number of days inactive after resolution}', function (CloseTicketAction $closeTicketAction) {
    $days = (int) $this->option('days');
    $count = (new AutoCloseResolvedTicketsJob($days))->handle($closeTicketAction);
    $this->info("Auto-closed {$count} inactive resolved ticket(s).");
})->purpose('Auto-close resolved tickets after inactivity threshold');

Artisan::command('idempotency:prune', function () {
    $count = (new PruneExpiredIdempotencyKeysJob)->handle();
    $this->info("Pruned {$count} expired idempotency key(s).");
})->purpose('Prune expired idempotency keys');

// Horizon metrics snapshot (every 5 minutes)
Schedule::command('horizon:snapshot')
    ->everyFiveMinutes()
    ->withoutOverlapping(10)
    ->onOneServer();

// Model pruner (daily)
Schedule::command('model:prune')
    ->daily()
    ->withoutOverlapping(30)
    ->onOneServer();

// Escalate overdue tickets (hourly)
Schedule::job(new EscalateOverdueTicketsJob(hoursOverdue: 24))
    ->hourly()
    ->withoutOverlapping(15)
    ->onOneServer();

// Auto-close resolved tickets inactive for 3+ days (daily at 02:00)
Schedule::job(new AutoCloseResolvedTicketsJob(daysAfterResolved: 3))
    ->dailyAt('02:00')
    ->withoutOverlapping(30)
    ->onOneServer();

// Prune expired idempotency keys (hourly)
Schedule::job(new PruneExpiredIdempotencyKeysJob)
    ->hourly()
    ->withoutOverlapping(15)
    ->onOneServer();

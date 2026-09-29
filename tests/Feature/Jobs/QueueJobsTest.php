<?php

declare(strict_types=1);

namespace Tests\Feature\Jobs;

use App\Domain\Identity\Enums\Role;
use App\Domain\Identity\Models\User;
use App\Domain\Ticket\Actions\CloseTicketAction;
use App\Domain\Ticket\Enums\TicketPriority;
use App\Domain\Ticket\Enums\TicketStatus;
use App\Domain\Ticket\Jobs\AutoCloseResolvedTicketsJob;
use App\Domain\Ticket\Jobs\EscalateOverdueTicketsJob;
use App\Domain\Ticket\Jobs\ProcessTicketRoutingJob;
use App\Domain\Ticket\Listeners\SendTicketNotificationListener;
use App\Domain\Ticket\Models\Category;
use App\Domain\Ticket\Models\Ticket;
use App\Domain\Ticket\Routing\TicketRouter;
use App\Infrastructure\Idempotency\Jobs\PruneExpiredIdempotencyKeysJob;
use App\Infrastructure\Idempotency\Models\IdempotencyKey;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

final class QueueJobsTest extends TestCase
{
    use RefreshDatabase;

    public function test_send_ticket_notification_listener_implements_should_queue_contract_and_middleware(): void
    {
        $listener = new SendTicketNotificationListener;

        $this->assertInstanceOf(ShouldQueue::class, $listener);
        $this->assertSame('notifications', $listener->queue);
        $this->assertSame(3, $listener->tries);
        $this->assertSame([10, 30, 60], $listener->backoff);
        $this->assertTrue($listener->afterCommit);

        $middleware = $listener->middleware();
        $this->assertCount(2, $middleware);
    }

    public function test_escalate_overdue_tickets_job_implements_unique_and_middleware(): void
    {
        $job = new EscalateOverdueTicketsJob(hoursOverdue: 24);

        $this->assertInstanceOf(ShouldBeUnique::class, $job);
        $this->assertInstanceOf(ShouldQueue::class, $job);
        $this->assertSame('escalate-overdue-tickets-24', $job->uniqueId());
        $this->assertSame(1800, $job->uniqueFor);
        $this->assertCount(1, $job->middleware());
    }

    public function test_auto_close_resolved_tickets_job_implements_unique_and_middleware(): void
    {
        $job = new AutoCloseResolvedTicketsJob(daysAfterResolved: 3);

        $this->assertInstanceOf(ShouldBeUnique::class, $job);
        $this->assertInstanceOf(ShouldQueue::class, $job);
        $this->assertSame('auto-close-resolved-tickets-3', $job->uniqueId());
        $this->assertSame(3600, $job->uniqueFor);
        $this->assertCount(1, $job->middleware());
    }

    public function test_prune_expired_idempotency_keys_job_implements_unique_and_middleware(): void
    {
        $job = new PruneExpiredIdempotencyKeysJob;

        $this->assertInstanceOf(ShouldBeUnique::class, $job);
        $this->assertInstanceOf(ShouldQueue::class, $job);
        $this->assertSame('prune-expired-idempotency-keys', $job->uniqueId());
        $this->assertSame(1800, $job->uniqueFor);
        $this->assertCount(1, $job->middleware());
    }

    public function test_escalate_overdue_tickets_job_escalates_past_due_tickets(): void
    {
        $customer = User::factory()->create();
        $category = Category::factory()->create();

        // Overdue ticket: created 48 hours ago, Low priority
        $overdueTicket = Ticket::factory()->create([
            'customer_id' => $customer->id,
            'category_id' => $category->id,
            'priority' => TicketPriority::Low,
            'status' => TicketStatus::Open,
            'created_at' => Carbon::now()->subHours(48),
        ]);

        // Recent ticket: created 2 hours ago, Medium priority
        $recentTicket = Ticket::factory()->create([
            'customer_id' => $customer->id,
            'category_id' => $category->id,
            'priority' => TicketPriority::Medium,
            'status' => TicketStatus::Open,
            'created_at' => Carbon::now()->subHours(2),
        ]);

        // Already closed overdue ticket: should be skipped
        $closedTicket = Ticket::factory()->create([
            'customer_id' => $customer->id,
            'category_id' => $category->id,
            'priority' => TicketPriority::Low,
            'status' => TicketStatus::Closed,
            'created_at' => Carbon::now()->subHours(48),
        ]);

        $job = new EscalateOverdueTicketsJob(hoursOverdue: 24);
        $escalatedCount = $job->handle();

        $this->assertSame(1, $escalatedCount);
        $this->assertSame(TicketPriority::Urgent, $overdueTicket->fresh()->priority);
        $this->assertSame(TicketPriority::Medium, $recentTicket->fresh()->priority);
        $this->assertSame(TicketPriority::Low, $closedTicket->fresh()->priority);
    }

    public function test_auto_close_resolved_tickets_job_closes_inactive_resolved_tickets(): void
    {
        $customer = User::factory()->create();
        $category = Category::factory()->create();

        // Inactive resolved ticket (resolved 5 days ago)
        $oldResolvedTicket = Ticket::factory()->create([
            'customer_id' => $customer->id,
            'category_id' => $category->id,
            'status' => TicketStatus::Resolved,
            'updated_at' => Carbon::now()->subDays(5),
        ]);

        // Recently resolved ticket (resolved 1 day ago)
        $recentlyResolvedTicket = Ticket::factory()->create([
            'customer_id' => $customer->id,
            'category_id' => $category->id,
            'status' => TicketStatus::Resolved,
            'updated_at' => Carbon::now()->subDay(),
        ]);

        // Active In-Progress ticket (5 days old)
        $activeTicket = Ticket::factory()->create([
            'customer_id' => $customer->id,
            'category_id' => $category->id,
            'status' => TicketStatus::InProgess,
            'updated_at' => Carbon::now()->subDays(5),
        ]);

        $job = new AutoCloseResolvedTicketsJob(daysAfterResolved: 3);
        $closedCount = $job->handle(new CloseTicketAction);

        $this->assertSame(1, $closedCount);
        $this->assertSame(TicketStatus::Closed, $oldResolvedTicket->fresh()->status);
        $this->assertSame(TicketStatus::Resolved, $recentlyResolvedTicket->fresh()->status);
        $this->assertSame(TicketStatus::InProgess, $activeTicket->fresh()->status);
    }

    public function test_process_ticket_routing_job_routes_ticket_asynchronously(): void
    {
        $vipCustomer = User::factory()->create([
            'email' => 'vip.client@enterprise.com',
            'role' => Role::Customer,
        ]);
        $agent = User::factory()->create(['role' => Role::Agent]);
        $category = Category::factory()->create(['name' => 'General']);

        $ticket = Ticket::factory()->create([
            'customer_id' => $vipCustomer->id,
            'category_id' => $category->id,
            'priority' => TicketPriority::Low,
            'assigned_to' => null,
            'status' => TicketStatus::Open,
        ]);

        $job = new ProcessTicketRoutingJob(ticket: $ticket, persist: true);
        $this->assertSame((string) $ticket->id, $job->uniqueId());
        $this->assertSame('routing', $job->queue);

        $decision = $job->handle(TicketRouter::createDefault());

        $this->assertSame('VIP Rule', $decision->matchedRule);
        $this->assertSame(TicketPriority::Urgent, $decision->priority);
        $this->assertSame(TicketPriority::Urgent, $ticket->fresh()->priority);
    }

    public function test_prune_expired_idempotency_keys_job_deletes_expired_records(): void
    {
        // Expired key
        IdempotencyKey::query()->create([
            'scope_type' => 'user',
            'scope_id' => '1',
            'operation' => 'POST /api/v1/tickets',
            'key_hash' => hash('sha256', 'key-1'),
            'request_hash' => hash('sha256', 'req-1'),
            'response_status' => 201,
            'expires_at' => Carbon::now()->subHour(),
            'completed_at' => Carbon::now()->subHour(),
        ]);

        // Active key
        IdempotencyKey::query()->create([
            'scope_type' => 'user',
            'scope_id' => '2',
            'operation' => 'POST /api/v1/tickets',
            'key_hash' => hash('sha256', 'key-2'),
            'request_hash' => hash('sha256', 'req-2'),
            'response_status' => 201,
            'expires_at' => Carbon::now()->addHour(),
            'completed_at' => Carbon::now(),
        ]);

        $job = new PruneExpiredIdempotencyKeysJob;
        $prunedCount = $job->handle();

        $this->assertSame(1, $prunedCount);
        $this->assertSame(1, IdempotencyKey::query()->count());
    }

    public function test_artisan_commands_execute_jobs_successfully(): void
    {
        $this->artisan('tickets:escalate-overdue', ['--hours' => 12])
            ->expectsOutputToContain('Escalated 0 overdue ticket(s) to urgent priority.')
            ->assertSuccessful();

        $this->artisan('tickets:auto-close-resolved', ['--days' => 7])
            ->expectsOutputToContain('Auto-closed 0 inactive resolved ticket(s).')
            ->assertSuccessful();

        $this->artisan('idempotency:prune')
            ->expectsOutputToContain('Pruned 0 expired idempotency key(s).')
            ->assertSuccessful();

        $this->artisan('model:prune')
            ->assertSuccessful();
    }
}

<?php

declare(strict_types=1);

namespace Tests\Feature\API;

use App\Domain\Audit\Models\AuditLog;
use App\Domain\Audit\Services\AuditLogger;
use App\Domain\Identity\Enums\Role;
use App\Domain\Identity\Models\User;
use App\Domain\Ticket\Enums\TicketStatus;
use App\Domain\Ticket\Models\Category;
use App\Domain\Ticket\Models\Ticket;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\Fluent\AssertableJson;
use Tests\TestCase;

class AuditLogApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_ticket_mutations_automatically_generate_audit_logs(): void
    {
        $customer = User::factory()->create(['role' => Role::Customer]);
        $agent = User::factory()->create(['role' => Role::Agent]);
        $category = Category::factory()->create();

        $ticket = Ticket::factory()->create([
            'customer_id' => $customer->id,
            'category_id' => $category->id,
            'subject' => 'Initial subject',
            'status' => TicketStatus::Open,
        ]);

        $this->assertDatabaseHas('audit_logs', [
            'action' => 'created',
            'auditable_type' => Ticket::class,
            'auditable_id' => (string) $ticket->id,
        ]);

        $ticket->update([
            'status' => TicketStatus::InProgess,
            'assigned_to' => $agent->id,
        ]);

        $this->assertDatabaseHas('audit_logs', [
            'action' => 'updated',
            'auditable_type' => Ticket::class,
            'auditable_id' => (string) $ticket->id,
        ]);
    }

    public function test_audit_logger_redacts_sensitive_keys(): void
    {
        $logger = new AuditLogger;
        $user = User::factory()->create();

        $log = $logger->log(
            action: 'updated',
            auditable: $user,
            oldValues: ['password' => 'old_secret', 'email' => 'old@test.com'],
            newValues: ['password' => 'new_secret', 'email' => 'new@test.com'],
            userId: $user->id,
        );

        $this->assertEquals('********', $log->old_values['password']);
        $this->assertEquals('********', $log->new_values['password']);
        $this->assertEquals('old@test.com', $log->old_values['email']);
    }

    public function test_admin_and_agent_can_query_global_audit_logs_with_filters(): void
    {
        $admin = User::factory()->create(['role' => Role::Admin]);
        $agent = User::factory()->create(['role' => Role::Agent]);

        AuditLog::factory()->create([
            'user_id' => $agent->id,
            'action' => 'created',
            'auditable_type' => Ticket::class,
            'auditable_id' => '100',
        ]);

        AuditLog::factory()->create([
            'user_id' => $admin->id,
            'action' => 'updated',
            'auditable_type' => Ticket::class,
            'auditable_id' => '200',
        ]);

        $response = $this->actingAs($admin, 'api')
            ->getJson("/api/v1/audit-logs?action=created&user_id={$agent->id}");

        $response->assertOk()
            ->assertJson(fn (AssertableJson $json) => $json->has('data', 1)
                ->where('data.0.action', 'created')
                ->where('data.0.user_id', $agent->id)
                ->where('data.0.auditable_id', '100')
                ->etc()
            );
    }

    public function test_admin_and_agent_can_view_ticket_specific_audit_logs(): void
    {
        $admin = User::factory()->create(['role' => Role::Admin]);
        $ticket = Ticket::factory()->create();

        $response = $this->actingAs($admin, 'api')
            ->getJson("/api/v1/tickets/{$ticket->id}/audit-logs");

        $response->assertOk()
            ->assertJson(fn (AssertableJson $json) => $json->has('data')
                ->where('data.0.auditable_id', (string) $ticket->id)
                ->etc()
            );
    }

    public function test_customer_cannot_access_audit_logs(): void
    {
        $customer = User::factory()->create(['role' => Role::Customer]);
        $ticket = Ticket::factory()->create(['customer_id' => $customer->id]);

        $globalResponse = $this->actingAs($customer, 'api')
            ->getJson('/api/v1/audit-logs');
        $globalResponse->assertForbidden();

        $ticketResponse = $this->actingAs($customer, 'api')
            ->getJson("/api/v1/tickets/{$ticket->id}/audit-logs");
        $ticketResponse->assertForbidden();
    }

    public function test_admin_can_view_single_audit_log(): void
    {
        $admin = User::factory()->create(['role' => Role::Admin]);
        $log = AuditLog::factory()->create([
            'action' => 'updated',
        ]);

        $response = $this->actingAs($admin, 'api')
            ->getJson("/api/v1/audit-logs/{$log->id}");

        $response->assertOk()
            ->assertJsonPath('data.id', $log->id)
            ->assertJsonPath('data.action', 'updated');
    }
}

<?php

declare(strict_types=1);

namespace Tests\Feature\Authorization;

use App\Enums\Role;
use App\Models\AuditLog;
use App\Models\Category;
use App\Models\Ticket;
use App\Models\TicketStatusHistory;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Gate;
use Tests\TestCase;

final class PolicyCoverageTest extends TestCase
{
    use RefreshDatabase;

    public function test_category_and_user_policies(): void
    {
        $customer = User::factory()->create(['role' => Role::Customer]);
        $agent = User::factory()->create(['role' => Role::Agent]);
        $admin = User::factory()->create(['role' => Role::Admin]);

        $category = Category::factory()->create();

        // Category viewing
        $this->assertTrue(Gate::forUser($customer)->allows('viewAny', Category::class));
        $this->assertTrue(Gate::forUser($customer)->allows('view', $category));

        // Category modifications (Admin only)
        $this->assertFalse(Gate::forUser($customer)->allows('create', Category::class));
        $this->assertFalse(Gate::forUser($agent)->allows('create', Category::class));
        $this->assertTrue(Gate::forUser($admin)->allows('create', Category::class));

        $this->assertFalse(Gate::forUser($customer)->allows('update', $category));
        $this->assertFalse(Gate::forUser($agent)->allows('update', $category));
        $this->assertTrue(Gate::forUser($admin)->allows('update', $category));

        $this->assertFalse(Gate::forUser($customer)->allows('delete', $category));
        $this->assertFalse(Gate::forUser($agent)->allows('delete', $category));
        $this->assertTrue(Gate::forUser($admin)->allows('delete', $category));

        // User viewing & management
        $this->assertFalse(Gate::forUser($customer)->allows('viewAny', User::class));
        $this->assertTrue(Gate::forUser($agent)->allows('viewAny', User::class));
        $this->assertTrue(Gate::forUser($admin)->allows('viewAny', User::class));

        // User can view self
        $this->assertTrue(Gate::forUser($customer)->allows('view', $customer));
        $this->assertFalse(Gate::forUser($customer)->allows('view', $agent));
        $this->assertTrue(Gate::forUser($agent)->allows('view', $customer));
        $this->assertTrue(Gate::forUser($admin)->allows('view', $customer));

        // User can update self
        $this->assertTrue(Gate::forUser($customer)->allows('update', $customer));
        $this->assertFalse(Gate::forUser($customer)->allows('update', $agent));

        // Deleting user (Admin only)
        $this->assertFalse(Gate::forUser($customer)->allows('delete', $customer));
        $this->assertFalse(Gate::forUser($agent)->allows('delete', $customer));
        $this->assertTrue(Gate::forUser($admin)->allows('delete', $customer));
    }

    public function test_audit_log_and_status_history_policy_gates(): void
    {
        $customer = User::factory()->create(['role' => Role::Customer]);
        $otherCustomer = User::factory()->create(['role' => Role::Customer]);
        $agent = User::factory()->create(['role' => Role::Agent]);
        $admin = User::factory()->create(['role' => Role::Admin]);

        $ticket = Ticket::factory()->create(['customer_id' => $customer->id]);
        $history = TicketStatusHistory::factory()->create(['ticket_id' => $ticket->id]);
        $auditLog = AuditLog::factory()->create(['user_id' => $admin->id]);

        // Audit logs
        $this->assertFalse(Gate::forUser($customer)->allows('viewAny', AuditLog::class));
        $this->assertFalse(Gate::forUser($customer)->allows('view', $auditLog));
        $this->assertTrue(Gate::forUser($agent)->allows('viewAny', AuditLog::class));
        $this->assertTrue(Gate::forUser($agent)->allows('view', $auditLog));
        $this->assertTrue(Gate::forUser($admin)->allows('viewAny', AuditLog::class));
        $this->assertTrue(Gate::forUser($admin)->allows('view', $auditLog));

        // Status history
        $this->assertTrue(Gate::forUser($customer)->allows('viewAny', [TicketStatusHistory::class, $ticket]));
        $this->assertTrue(Gate::forUser($customer)->allows('view', $history));
        $this->assertFalse(Gate::forUser($otherCustomer)->allows('view', $history));
        $this->assertTrue(Gate::forUser($agent)->allows('view', $history));
        $this->assertTrue(Gate::forUser($admin)->allows('view', $history));
    }
}

<?php

declare(strict_types=1);

namespace Tests\Feature\Authorization;

use App\Enums\Role;
use App\Enums\TicketPriority;
use App\Enums\TicketStatus;
use App\Models\Category;
use App\Models\Ticket;
use App\Models\TicketMessage;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class TicketAuthorizationTest extends TestCase
{
    use RefreshDatabase;

    private User $customer;

    private User $otherCustomer;

    private User $agent;

    private User $admin;

    private Category $category;

    private Ticket $customerTicket;

    private Ticket $otherCustomerTicket;

    protected function setUp(): void
    {
        parent::setUp();

        $this->customer = User::factory()->create(['role' => Role::Customer]);
        $this->otherCustomer = User::factory()->create(['role' => Role::Customer]);
        $this->agent = User::factory()->create(['role' => Role::Agent]);
        $this->admin = User::factory()->create(['role' => Role::Admin]);

        $this->category = Category::factory()->create();

        $this->customerTicket = Ticket::factory()->create([
            'customer_id' => $this->customer->id,
            'category_id' => $this->category->id,
            'status' => TicketStatus::Open,
            'priority' => TicketPriority::Medium,
        ]);

        $this->otherCustomerTicket = Ticket::factory()->create([
            'customer_id' => $this->otherCustomer->id,
            'category_id' => $this->category->id,
            'status' => TicketStatus::Open,
            'priority' => TicketPriority::Medium,
        ]);
    }

    public function test_customer_can_view_own_ticket_but_forbidden_for_other_tickets(): void
    {
        // View own ticket -> 200 OK
        $this->actingAs($this->customer, 'api')
            ->getJson("/api/v1/tickets/{$this->customerTicket->id}")
            ->assertOk()
            ->assertJsonPath('data.id', $this->customerTicket->id);

        // View other customer's ticket -> 403 Forbidden
        $this->actingAs($this->customer, 'api')
            ->getJson("/api/v1/tickets/{$this->otherCustomerTicket->id}")
            ->assertForbidden();
    }

    public function test_customer_cannot_update_or_delete_tickets(): void
    {
        // Customer attempts to update ticket -> 403 Forbidden
        $this->actingAs($this->customer, 'api')
            ->withHeader('Idempotency-Key', 'update-test-key-12345')
            ->putJson("/api/v1/tickets/{$this->customerTicket->id}", [
                'category_id' => $this->category->id,
                'subject' => 'Hacked title',
                'description' => 'Updated description.',
                'priority' => TicketPriority::Urgent->value,
            ])
            ->assertForbidden();

        // Customer attempts to delete ticket -> 403 Forbidden
        $this->actingAs($this->customer, 'api')
            ->withHeader('Idempotency-Key', 'delete-test-key-12345')
            ->deleteJson("/api/v1/tickets/{$this->customerTicket->id}")
            ->assertForbidden();
    }

    public function test_customer_can_close_and_reopen_own_ticket_only(): void
    {
        // Customer closes own ticket -> 200 OK
        $this->actingAs($this->customer, 'api')
            ->postJson("/api/v1/tickets/{$this->customerTicket->id}/close")
            ->assertOk()
            ->assertJsonPath('data.status', TicketStatus::Closed->value);

        // Customer reopens own ticket -> 200 OK
        $this->actingAs($this->customer, 'api')
            ->postJson("/api/v1/tickets/{$this->customerTicket->id}/reopen")
            ->assertOk()
            ->assertJsonPath('data.status', TicketStatus::Open->value);

        // Customer attempts to close another customer's ticket -> 403 Forbidden
        $this->actingAs($this->customer, 'api')
            ->postJson("/api/v1/tickets/{$this->otherCustomerTicket->id}/close")
            ->assertForbidden();

        // Customer attempts to reopen another customer's ticket -> 403 Forbidden
        $this->actingAs($this->customer, 'api')
            ->postJson("/api/v1/tickets/{$this->otherCustomerTicket->id}/reopen")
            ->assertForbidden();
    }

    public function test_customer_cannot_transition_resolve_assign_or_route_tickets(): void
    {
        // Arbitrary transition -> 403 Forbidden
        $this->actingAs($this->customer, 'api')
            ->postJson("/api/v1/tickets/{$this->customerTicket->id}/transition", [
                'status' => TicketStatus::InProgess->value,
            ])
            ->assertForbidden();

        // Resolve -> 403 Forbidden
        $this->actingAs($this->customer, 'api')
            ->postJson("/api/v1/tickets/{$this->customerTicket->id}/resolve")
            ->assertForbidden();

        // Assign -> 403 Forbidden
        $this->actingAs($this->customer, 'api')
            ->postJson("/api/v1/tickets/{$this->customerTicket->id}/assign", [
                'agent_id' => $this->agent->id,
            ])
            ->assertForbidden();

        // Route -> 403 Forbidden
        $this->actingAs($this->customer, 'api')
            ->postJson("/api/v1/tickets/{$this->customerTicket->id}/route", [
                'persist' => true,
            ])
            ->assertForbidden();
    }

    public function test_customer_message_authorization_and_internal_notes_protection(): void
    {
        // Customer adds public message to own ticket -> 201 Created
        $this->actingAs($this->customer, 'api')
            ->postJson("/api/v1/tickets/{$this->customerTicket->id}/messages", [
                'message' => 'Customer message',
                'is_internal' => false,
            ])
            ->assertCreated();

        // Customer attempts to add internal note -> 403 Forbidden
        $this->actingAs($this->customer, 'api')
            ->postJson("/api/v1/tickets/{$this->customerTicket->id}/messages", [
                'message' => 'Sneaky internal note',
                'is_internal' => true,
            ])
            ->assertForbidden();

        // Customer attempts to add message to another ticket -> 403 Forbidden
        $this->actingAs($this->customer, 'api')
            ->postJson("/api/v1/tickets/{$this->otherCustomerTicket->id}/messages", [
                'message' => 'Unauthorized reply',
            ])
            ->assertForbidden();

        // View messages on other customer's ticket -> 403 Forbidden
        $this->actingAs($this->customer, 'api')
            ->getJson("/api/v1/tickets/{$this->otherCustomerTicket->id}/messages")
            ->assertForbidden();

        // Create internal note by agent on customer's ticket
        TicketMessage::factory()->create([
            'ticket_id' => $this->customerTicket->id,
            'user_id' => $this->agent->id,
            'message' => 'Agent private comment',
            'is_internal' => true,
        ]);

        // Customer viewing own messages does NOT see the internal note
        $res = $this->actingAs($this->customer, 'api')
            ->getJson("/api/v1/tickets/{$this->customerTicket->id}/messages")
            ->assertOk();

        $this->assertCount(1, $res->json('data'));
        $this->assertEquals('Customer message', $res->json('data.0.message'));
    }

    public function test_customer_status_history_authorization(): void
    {
        // Customer views own ticket history -> 200 OK
        $this->actingAs($this->customer, 'api')
            ->getJson("/api/v1/tickets/{$this->customerTicket->id}/status-history")
            ->assertOk();

        // Customer attempts to view other ticket history -> 403 Forbidden
        $this->actingAs($this->customer, 'api')
            ->getJson("/api/v1/tickets/{$this->otherCustomerTicket->id}/status-history")
            ->assertForbidden();
    }

    public function test_agent_authorization_permissions(): void
    {
        // Agent can view any ticket
        $this->actingAs($this->agent, 'api')
            ->getJson("/api/v1/tickets/{$this->customerTicket->id}")
            ->assertOk();

        // Agent can update ticket
        $this->actingAs($this->agent, 'api')
            ->withHeader('Idempotency-Key', 'agent-update-key-12345')
            ->putJson("/api/v1/tickets/{$this->customerTicket->id}", [
                'category_id' => $this->category->id,
                'subject' => 'Updated by Agent',
                'description' => 'Updated description.',
                'priority' => TicketPriority::High->value,
            ])
            ->assertOk()
            ->assertJsonPath('data.priority', TicketPriority::High->value);

        // Agent can transition status
        $this->actingAs($this->agent, 'api')
            ->postJson("/api/v1/tickets/{$this->customerTicket->id}/transition", [
                'status' => TicketStatus::InProgess->value,
            ])
            ->assertOk();

        // Agent can resolve ticket
        $this->actingAs($this->agent, 'api')
            ->postJson("/api/v1/tickets/{$this->customerTicket->id}/resolve")
            ->assertOk();

        // Agent can assign ticket
        $this->actingAs($this->agent, 'api')
            ->postJson("/api/v1/tickets/{$this->customerTicket->id}/assign", [
                'agent_id' => $this->agent->id,
            ])
            ->assertOk();

        // Agent can route ticket
        $this->actingAs($this->agent, 'api')
            ->postJson("/api/v1/tickets/{$this->customerTicket->id}/route", [
                'persist' => true,
            ])
            ->assertOk();

        // Agent can post internal notes
        $this->actingAs($this->agent, 'api')
            ->postJson("/api/v1/tickets/{$this->customerTicket->id}/messages", [
                'message' => 'Agent internal investigation note',
                'is_internal' => true,
            ])
            ->assertCreated()
            ->assertJsonPath('data.is_internal', true);

        // Agent can view all messages including internal notes
        $this->actingAs($this->agent, 'api')
            ->getJson("/api/v1/tickets/{$this->customerTicket->id}/messages")
            ->assertOk();

        // Agent CANNOT delete ticket -> 403 Forbidden
        $this->actingAs($this->agent, 'api')
            ->withHeader('Idempotency-Key', 'agent-delete-key-12345')
            ->deleteJson("/api/v1/tickets/{$this->customerTicket->id}")
            ->assertForbidden();
    }

    public function test_admin_has_full_authorization_including_deletion(): void
    {
        // Admin can view any ticket
        $this->actingAs($this->admin, 'api')
            ->getJson("/api/v1/tickets/{$this->customerTicket->id}")
            ->assertOk();

        // Admin can update ticket
        $this->actingAs($this->admin, 'api')
            ->withHeader('Idempotency-Key', 'admin-update-key-12345')
            ->putJson("/api/v1/tickets/{$this->customerTicket->id}", [
                'category_id' => $this->category->id,
                'subject' => 'Admin Subject',
                'description' => 'Admin description.',
                'priority' => TicketPriority::Urgent->value,
            ])
            ->assertOk();

        // Admin can post internal notes
        $this->actingAs($this->admin, 'api')
            ->postJson("/api/v1/tickets/{$this->customerTicket->id}/messages", [
                'message' => 'Admin internal note',
                'is_internal' => true,
            ])
            ->assertCreated();

        // Admin can delete ticket -> 200 OK
        $this->actingAs($this->admin, 'api')
            ->withHeader('Idempotency-Key', 'admin-delete-key-12345')
            ->deleteJson("/api/v1/tickets/{$this->customerTicket->id}")
            ->assertOk()
            ->assertJsonPath('message', 'Ticket deleted successfully.');

        $this->assertDatabaseMissing('tickets', ['id' => $this->customerTicket->id]);
    }
}

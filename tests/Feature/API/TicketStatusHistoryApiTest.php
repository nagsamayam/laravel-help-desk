<?php

declare(strict_types=1);

namespace Tests\Feature\API;

use App\Domain\Identity\Enums\Role;
use App\Domain\Identity\Models\User;
use App\Domain\Ticket\Enums\TicketStatus;
use App\Domain\Ticket\Models\Category;
use App\Domain\Ticket\Models\Ticket;
use App\Domain\Ticket\Models\TicketStatusHistory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\Fluent\AssertableJson;
use Tests\TestCase;

class TicketStatusHistoryApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_creating_a_ticket_records_initial_status_history(): void
    {
        $customer = User::factory()->create(['role' => Role::Customer]);
        $category = Category::factory()->create();

        $ticket = Ticket::factory()->create([
            'customer_id' => $customer->id,
            'category_id' => $category->id,
            'status' => TicketStatus::Open,
        ]);

        $this->assertDatabaseHas('ticket_status_histories', [
            'ticket_id' => $ticket->id,
            'from_status' => null,
            'to_status' => TicketStatus::Open->value,
        ]);
    }

    public function test_transitioning_status_via_api_records_status_history_with_reason(): void
    {
        $agent = User::factory()->create(['role' => Role::Agent]);
        $customer = User::factory()->create(['role' => Role::Customer]);
        $category = Category::factory()->create();

        $ticket = Ticket::factory()->create([
            'customer_id' => $customer->id,
            'category_id' => $category->id,
            'status' => TicketStatus::Open,
        ]);

        $response = $this->actingAs($agent, 'api')->postJson("/api/v1/tickets/{$ticket->id}/transition", [
            'status' => TicketStatus::InProgess->value,
            'reason' => 'Agent started investigating the issue',
        ]);

        $response->assertOk();

        $this->assertDatabaseHas('ticket_status_histories', [
            'ticket_id' => $ticket->id,
            'from_status' => TicketStatus::Open->value,
            'to_status' => TicketStatus::InProgess->value,
            'changed_by' => $agent->id,
            'reason' => 'Agent started investigating the issue',
        ]);
    }

    public function test_customer_can_view_own_ticket_status_history(): void
    {
        $customer = User::factory()->create(['role' => Role::Customer]);
        $ticket = Ticket::factory()->create([
            'customer_id' => $customer->id,
            'status' => TicketStatus::Open,
        ]);

        TicketStatusHistory::factory()->create([
            'ticket_id' => $ticket->id,
            'from_status' => TicketStatus::Open,
            'to_status' => TicketStatus::InProgess,
            'changed_by' => $customer->id,
            'reason' => 'Customer added notes',
        ]);

        $response = $this->actingAs($customer, 'api')
            ->getJson("/api/v1/tickets/{$ticket->id}/status-history");

        $response->assertOk()
            ->assertJson(fn (AssertableJson $json) => $json->has('data', 2)
                ->has('data.0', fn (AssertableJson $item) => $item->where('ticket_id', $ticket->id)
                    ->has('from_status')
                    ->has('to_status')
                    ->has('changed_by')
                    ->has('reason')
                    ->has('created_at')
                    ->etc()
                )
                ->etc()
            );

        // Also test /history alias
        $aliasResponse = $this->actingAs($customer, 'api')
            ->getJson("/api/v1/tickets/{$ticket->id}/history");

        $aliasResponse->assertOk();
    }

    public function test_customer_cannot_view_other_customers_ticket_status_history(): void
    {
        $owner = User::factory()->create(['role' => Role::Customer]);
        $otherCustomer = User::factory()->create(['role' => Role::Customer]);

        $ticket = Ticket::factory()->create([
            'customer_id' => $owner->id,
            'status' => TicketStatus::Open,
        ]);

        $response = $this->actingAs($otherCustomer, 'api')
            ->getJson("/api/v1/tickets/{$ticket->id}/status-history");

        $response->assertForbidden();
    }

    public function test_agent_and_admin_can_view_any_ticket_status_history(): void
    {
        $admin = User::factory()->create(['role' => Role::Admin]);
        $agent = User::factory()->create(['role' => Role::Agent]);
        $customer = User::factory()->create(['role' => Role::Customer]);

        $ticket = Ticket::factory()->create([
            'customer_id' => $customer->id,
            'status' => TicketStatus::Open,
        ]);

        $agentResponse = $this->actingAs($agent, 'api')
            ->getJson("/api/v1/tickets/{$ticket->id}/status-history");
        $agentResponse->assertOk();

        $adminResponse = $this->actingAs($admin, 'api')
            ->getJson("/api/v1/tickets/{$ticket->id}/status-history");
        $adminResponse->assertOk();
    }
}

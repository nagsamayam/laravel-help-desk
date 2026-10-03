<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Domain\Identity\Enums\Role;
use App\Domain\Identity\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Gate;
use Tests\TestCase;

class HorizonTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_access_horizon_gate(): void
    {
        $admin = User::factory()->create([
            'role' => Role::Admin,
        ]);

        $this->assertTrue(Gate::forUser($admin)->allows('viewHorizon'));
    }

    public function test_customer_and_agent_cannot_access_horizon_gate(): void
    {
        $agent = User::factory()->create([
            'role' => Role::Agent,
        ]);

        $customer = User::factory()->create([
            'role' => Role::Customer,
        ]);

        $this->assertFalse(Gate::forUser($agent)->allows('viewHorizon'));
        $this->assertFalse(Gate::forUser($customer)->allows('viewHorizon'));
    }

    public function test_guest_cannot_access_horizon_gate(): void
    {
        $this->assertFalse(Gate::forUser(null)->allows('viewHorizon'));
    }

    public function test_admin_can_access_horizon_dashboard_http_route(): void
    {
        $admin = User::factory()->create([
            'role' => Role::Admin,
        ]);

        $response = $this->actingAs($admin, 'api')->get('/horizon');

        $response->assertOk();
    }

    public function test_agent_cannot_access_horizon_dashboard_http_route(): void
    {
        $agent = User::factory()->create([
            'role' => Role::Agent,
        ]);

        $response = $this->actingAs($agent, 'api')->get('/horizon');

        $response->assertForbidden();
    }

    public function test_customer_cannot_access_horizon_dashboard_http_route(): void
    {
        $customer = User::factory()->create([
            'role' => Role::Customer,
        ]);

        $response = $this->actingAs($customer, 'api')->get('/horizon');

        $response->assertForbidden();
    }

    public function test_admin_can_access_horizon_dashboard_with_query_token(): void
    {
        $admin = User::factory()->create([
            'role' => Role::Admin,
        ]);

        $token = auth('api')->tokenById($admin->id);

        $response = $this->get('/horizon?token='.$token);

        $response->assertOk();
    }

    public function test_agent_cannot_access_horizon_dashboard_with_query_token(): void
    {
        $agent = User::factory()->create([
            'role' => Role::Agent,
        ]);

        $token = auth('api')->tokenById($agent->id);

        $response = $this->get('/horizon?token='.$token);

        $response->assertForbidden();
    }

    public function test_guest_cannot_access_horizon_dashboard_http_route(): void
    {
        $response = $this->get('/horizon');

        $response->assertForbidden();
    }

    public function test_guest_can_access_horizon_dashboard_in_local_environment(): void
    {
        app()['env'] = 'local';

        $response = $this->get('/horizon');

        $response->assertOk();
    }
}

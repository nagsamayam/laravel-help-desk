<?php

declare(strict_types=1);

use App\Enums\Role;
use App\Enums\TicketStatus;
use App\Models\Category;
use App\Models\Ticket;
use App\Models\User;
use App\Services\Assignment\TicketAssignmentService;
use App\Strategies\Assignment\LeastBusyAgentAssignment;
use App\Strategies\Assignment\RoundRobinAssignment;
use App\Strategies\Assignment\SkillBasedAssignment;
use Illuminate\Support\Facades\Cache;

beforeEach(function (): void {
    Cache::flush();
    $this->category = Category::factory()->create();
    $this->customer = User::factory()->create(['role' => Role::Customer]);
});

test('round robin strategy assigns tickets cyclically across available agents', function (): void {
    $agentA = User::factory()->create(['role' => Role::Agent]);
    $agentB = User::factory()->create(['role' => Role::Agent]);
    $agentC = User::factory()->create(['role' => Role::Agent]);

    $agents = collect([$agentA, $agentB, $agentC]);

    $strategy = new RoundRobinAssignment(cacheKey: 'test:round_robin');
    $service = new TicketAssignmentService($strategy);

    $ticket1 = Ticket::factory()->create(['category_id' => $this->category->id, 'customer_id' => $this->customer->id]);
    $ticket2 = Ticket::factory()->create(['category_id' => $this->category->id, 'customer_id' => $this->customer->id]);
    $ticket3 = Ticket::factory()->create(['category_id' => $this->category->id, 'customer_id' => $this->customer->id]);
    $ticket4 = Ticket::factory()->create(['category_id' => $this->category->id, 'customer_id' => $this->customer->id]);

    $assigned1 = $service->assign($ticket1, candidates: $agents);
    $assigned2 = $service->assign($ticket2, candidates: $agents);
    $assigned3 = $service->assign($ticket3, candidates: $agents);
    $assigned4 = $service->assign($ticket4, candidates: $agents);

    expect($assigned1->id)->toBe($agentA->id)
        ->and($assigned2->id)->toBe($agentB->id)
        ->and($assigned3->id)->toBe($agentC->id)
        ->and($assigned4->id)->toBe($agentA->id)
        ->and($ticket1->fresh()->assigned_to)->toBe($agentA->id)
        ->and($ticket2->fresh()->assigned_to)->toBe($agentB->id);
});

test('least busy agent strategy selects agent with fewest active tickets', function (): void {
    $busyAgent = User::factory()->create(['role' => Role::Agent]);
    $freeAgent = User::factory()->create(['role' => Role::Agent]);

    // Give busyAgent 2 active tickets
    Ticket::factory()->create([
        'assigned_to' => $busyAgent->id,
        'status' => TicketStatus::Open,
        'category_id' => $this->category->id,
        'customer_id' => $this->customer->id,
    ]);
    Ticket::factory()->create([
        'assigned_to' => $busyAgent->id,
        'status' => TicketStatus::InProgess,
        'category_id' => $this->category->id,
        'customer_id' => $this->customer->id,
    ]);

    // Give freeAgent 1 closed ticket (inactive)
    Ticket::factory()->create([
        'assigned_to' => $freeAgent->id,
        'status' => TicketStatus::Closed,
        'category_id' => $this->category->id,
        'customer_id' => $this->customer->id,
    ]);

    $ticket = Ticket::factory()->create(['category_id' => $this->category->id, 'customer_id' => $this->customer->id]);

    $service = new TicketAssignmentService(new LeastBusyAgentAssignment);
    $assigned = $service->assign($ticket, candidates: [$busyAgent, $freeAgent]);

    expect($assigned->id)->toBe($freeAgent->id)
        ->and($ticket->fresh()->assigned_to)->toBe($freeAgent->id);
});

test('skill based strategy assigns ticket to agent with matching skill/category', function (): void {
    $techCategory = Category::factory()->create(['name' => 'Technical Support']);
    $billingCategory = Category::factory()->create(['name' => 'Billing']);

    $techAgent = User::factory()->create(['role' => Role::Agent]);
    $billingAgent = User::factory()->create(['role' => Role::Agent]);

    $agentSkillsMap = [
        $techAgent->id => [$techCategory->id, 'Technical Support'],
        $billingAgent->id => [$billingCategory->id, 'Billing'],
    ];

    $strategy = new SkillBasedAssignment(agentSkillsMap: $agentSkillsMap);
    $service = new TicketAssignmentService($strategy);

    $ticketTech = Ticket::factory()->create([
        'category_id' => $techCategory->id,
        'customer_id' => $this->customer->id,
    ]);
    $ticketBilling = Ticket::factory()->create([
        'category_id' => $billingCategory->id,
        'customer_id' => $this->customer->id,
    ]);

    $assignedTech = $service->assign($ticketTech, candidates: [$techAgent, $billingAgent]);
    $assignedBilling = $service->assign($ticketBilling, candidates: [$techAgent, $billingAgent]);

    expect($assignedTech->id)->toBe($techAgent->id)
        ->and($assignedBilling->id)->toBe($billingAgent->id);
});

test('skill based strategy falls back gracefully when no matching skills found', function (): void {
    $agentA = User::factory()->create(['role' => Role::Agent]);
    $strategy = new SkillBasedAssignment(agentSkillsMap: []);
    $service = new TicketAssignmentService($strategy);

    $ticket = Ticket::factory()->create([
        'category_id' => $this->category->id,
        'customer_id' => $this->customer->id,
    ]);

    $assigned = $service->assign($ticket, candidates: [$agentA]);
    expect($assigned->id)->toBe($agentA->id);
});

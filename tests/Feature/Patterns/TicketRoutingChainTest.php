<?php

declare(strict_types=1);

use App\Domain\Identity\Enums\Role;
use App\Domain\Identity\Models\User;
use App\Domain\Ticket\Enums\TicketPriority;
use App\Domain\Ticket\Models\Category;
use App\Domain\Ticket\Models\Ticket;
use App\Domain\Ticket\Routing\TicketRouter;

beforeEach(function (): void {
    $this->category = Category::factory()->create(['name' => 'General Inquiry']);
    $this->customer = User::factory()->create(['email' => 'regular@example.com', 'role' => Role::Customer]);
});

test('vip routing rule matches vip customer and escalates priority to urgent', function (): void {
    $vipCustomer = User::factory()->create(['email' => 'ceo@enterprise.com', 'role' => Role::Customer]);

    $ticket = Ticket::factory()->create([
        'subject' => 'Need standard help',
        'description' => 'Regular query',
        'priority' => TicketPriority::Low,
        'category_id' => $this->category->id,
        'customer_id' => $vipCustomer->id,
    ]);

    $router = TicketRouter::createDefault();
    $decision = $router->route($ticket, persist: true);

    expect($decision->matchedRule)->toBe('VIP Rule')
        ->and($decision->priority)->toBe(TicketPriority::Urgent)
        ->and($ticket->fresh()->priority)->toBe(TicketPriority::Urgent)
        ->and($decision->tags)->toContain('vip');
});

test('urgent routing rule matches critical keywords in subject and sets urgent priority', function (): void {
    $ticket = Ticket::factory()->create([
        'subject' => 'Production system down - crash emergency',
        'description' => 'Users cannot login',
        'priority' => TicketPriority::Low,
        'category_id' => $this->category->id,
        'customer_id' => $this->customer->id,
    ]);

    $router = TicketRouter::createDefault();
    $decision = $router->route($ticket, persist: true);

    expect($decision->matchedRule)->toBe('Urgent Rule')
        ->and($decision->priority)->toBe(TicketPriority::Urgent)
        ->and($ticket->fresh()->priority)->toBe(TicketPriority::Urgent)
        ->and($decision->tags)->toContain('urgent');
});

test('category routing rule matches specific category and sets mapped priority', function (): void {
    $securityCategory = Category::factory()->create(['name' => 'Security Vulnerability']);

    $ticket = Ticket::factory()->create([
        'subject' => 'Password reset issue',
        'description' => 'Cannot reset password token',
        'priority' => TicketPriority::Low,
        'category_id' => $securityCategory->id,
        'customer_id' => $this->customer->id,
    ]);

    $router = TicketRouter::createDefault();
    $decision = $router->route($ticket, persist: true);

    expect($decision->matchedRule)->toBe('Category Rule')
        ->and($decision->priority)->toBe(TicketPriority::High)
        ->and($ticket->fresh()->priority)->toBe(TicketPriority::High);
});

test('default routing rule serves as fallback when preceding rules do not match', function (): void {
    $ticket = Ticket::factory()->create([
        'subject' => 'How do I change my avatar?',
        'description' => 'Minor question about profile picture',
        'priority' => TicketPriority::Low,
        'category_id' => $this->category->id,
        'customer_id' => $this->customer->id,
    ]);

    $router = TicketRouter::createDefault();
    $decision = $router->route($ticket, persist: true);

    expect($decision->matchedRule)->toBe('Default Rule')
        ->and($decision->priority)->toBe(TicketPriority::Low)
        ->and($decision->tags)->toContain('default');
});

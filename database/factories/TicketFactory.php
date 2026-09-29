<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Domain\Identity\Models\User;
use App\Domain\Ticket\Enums\TicketPriority;
use App\Domain\Ticket\Enums\TicketStatus;
use App\Domain\Ticket\Models\Category;
use App\Domain\Ticket\Models\Ticket;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Ticket>
 */
class TicketFactory extends Factory
{
    protected $model = Ticket::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'subject' => fake()->sentence(),
            'description' => fake()->paragraph(),
            'status' => TicketStatus::Open,
            'priority' => TicketPriority::Low,
            'category_id' => Category::factory(),
            'customer_id' => User::factory(),
            'assigned_to' => null,
        ];
    }

    public function open(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => TicketStatus::Open,
        ]);
    }

    public function inProgress(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => TicketStatus::InProgess,
        ]);
    }

    public function waitingForCustomer(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => TicketStatus::WaitingForCustomer,
        ]);
    }

    public function resolved(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => TicketStatus::Resolved,
        ]);
    }

    public function closed(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => TicketStatus::Closed,
        ]);
    }

    public function low(): static
    {
        return $this->state(fn (array $attributes) => [
            'priority' => TicketPriority::Low,
        ]);
    }

    public function medium(): static
    {
        return $this->state(fn (array $attributes) => [
            'priority' => TicketPriority::Medium,
        ]);
    }

    public function high(): static
    {
        return $this->state(fn (array $attributes) => [
            'priority' => TicketPriority::High,
        ]);
    }

    public function urgent(): static
    {
        return $this->state(fn (array $attributes) => [
            'priority' => TicketPriority::Urgent,
        ]);
    }

    public function assignedTo(User|int $agent): static
    {
        return $this->state(fn (array $attributes) => [
            'assigned_to' => $agent instanceof User ? $agent->id : $agent,
        ]);
    }

    public function unassigned(): static
    {
        return $this->state(fn (array $attributes) => [
            'assigned_to' => null,
        ]);
    }

    public function forCustomer(User|int $customer): static
    {
        return $this->state(fn (array $attributes) => [
            'customer_id' => $customer instanceof User ? $customer->id : $customer,
        ]);
    }

    public function forCategory(Category|int $category): static
    {
        return $this->state(fn (array $attributes) => [
            'category_id' => $category instanceof Category ? $category->id : $category,
        ]);
    }

    public function overdue(): static
    {
        return $this->state(fn (array $attributes) => [
            'created_at' => now()->subDays(5),
            'updated_at' => now()->subDays(5),
        ]);
    }
}

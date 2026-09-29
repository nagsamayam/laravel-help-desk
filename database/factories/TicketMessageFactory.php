<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Domain\Identity\Models\User;
use App\Domain\Ticket\Models\Ticket;
use App\Domain\Ticket\Models\TicketMessage;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<TicketMessage>
 */
class TicketMessageFactory extends Factory
{
    protected $model = TicketMessage::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'ticket_id' => Ticket::factory(),
            'user_id' => User::factory(),
            'message' => fake()->paragraph(),
            'is_internal' => false,
        ];
    }

    public function internal(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_internal' => true,
        ]);
    }

    public function publicMessage(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_internal' => false,
        ]);
    }

    public function forTicket(Ticket|int $ticket): static
    {
        return $this->state(fn (array $attributes) => [
            'ticket_id' => $ticket instanceof Ticket ? $ticket->id : $ticket,
        ]);
    }

    public function byUser(User|int $user): static
    {
        return $this->state(fn (array $attributes) => [
            'user_id' => $user instanceof User ? $user->id : $user,
        ]);
    }
}

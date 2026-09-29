<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Domain\Identity\Models\User;
use App\Domain\Ticket\Enums\TicketStatus;
use App\Domain\Ticket\Models\Ticket;
use App\Domain\Ticket\Models\TicketStatusHistory;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<TicketStatusHistory>
 */
class TicketStatusHistoryFactory extends Factory
{
    protected $model = TicketStatusHistory::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'ticket_id' => Ticket::factory(),
            'from_status' => TicketStatus::Open,
            'to_status' => TicketStatus::InProgess,
            'changed_by' => User::factory(),
            'reason' => fake()->sentence(),
            'created_at' => now(),
        ];
    }

    public function transition(TicketStatus $from, TicketStatus $to): static
    {
        return $this->state(fn (array $attributes) => [
            'from_status' => $from,
            'to_status' => $to,
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
            'changed_by' => $user instanceof User ? $user->id : $user,
        ]);
    }
}

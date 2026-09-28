<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\TicketStatus;
use App\Models\Ticket;
use App\Models\TicketStatusHistory;
use App\Models\User;
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
}

<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Domain\Identity\Models\User;
use App\Domain\Ticket\Models\Ticket;
use App\Domain\Ticket\Models\TicketAttachment;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<TicketAttachment>
 */
class TicketAttachmentFactory extends Factory
{
    protected $model = TicketAttachment::class;

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
            'original_name' => fake()->lexify('document-????').'.pdf',
            'file_path' => 'attachments/'.fake()->uuid().'.pdf',
            'disk' => 'public',
            'mime_type' => 'application/pdf',
            'file_size' => fake()->numberBetween(1024, 1024 * 1024 * 2), // 1KB to 2MB
        ];
    }

    public function image(): static
    {
        return $this->state(fn (array $attributes) => [
            'original_name' => fake()->lexify('screenshot-????').'.png',
            'file_path' => 'attachments/'.fake()->uuid().'.png',
            'mime_type' => 'image/png',
        ]);
    }

    public function forTicket(Ticket|int $ticket): static
    {
        return $this->state(fn (array $attributes) => [
            'ticket_id' => $ticket instanceof Ticket ? $ticket->id : $ticket,
        ]);
    }

    public function forUser(User|int $user): static
    {
        return $this->state(fn (array $attributes) => [
            'user_id' => $user instanceof User ? $user->id : $user,
        ]);
    }

    public function unattached(): static
    {
        return $this->state(fn (array $attributes) => [
            'ticket_id' => null,
        ]);
    }
}

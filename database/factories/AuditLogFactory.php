<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Domain\Audit\Models\AuditLog;
use App\Domain\Identity\Models\User;
use App\Domain\Ticket\Models\Ticket;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Model;

/**
 * @extends Factory<AuditLog>
 */
class AuditLogFactory extends Factory
{
    protected $model = AuditLog::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'action' => 'updated',
            'auditable_type' => Ticket::class,
            'auditable_id' => Ticket::factory(),
            'old_values' => ['status' => 'open'],
            'new_values' => ['status' => 'in_progress'],
            'ip_address' => fake()->ipv4(),
            'user_agent' => fake()->userAgent(),
            'created_at' => now(),
        ];
    }

    public function forAuditable(Model|int $model, ?string $type = null): static
    {
        return $this->state(fn (array $attributes) => [
            'auditable_id' => $model instanceof Model ? $model->getKey() : $model,
            'auditable_type' => $model instanceof Model ? $model->getMorphClass() : ($type ?? Ticket::class),
        ]);
    }

    public function forUser(User|int $user): static
    {
        return $this->state(fn (array $attributes) => [
            'user_id' => $user instanceof User ? $user->id : $user,
        ]);
    }

    public function withAction(string $action, array $oldValues = [], array $newValues = []): static
    {
        return $this->state(fn (array $attributes) => [
            'action' => $action,
            'old_values' => $oldValues,
            'new_values' => $newValues,
        ]);
    }
}

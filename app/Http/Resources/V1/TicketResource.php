<?php

declare(strict_types=1);

namespace App\Http\Resources\V1;

use App\Models\Ticket;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Ticket
 */
class TicketResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $assigneeUser = $this->relationLoaded('assignee')
            ? $this->assignee
            : ($this->relationLoaded('assigne') ? $this->assigne : null);

        return [
            'id' => $this->id,
            'subject' => $this->subject,
            'title' => $this->subject,
            'description' => $this->description,
            'status' => $this->status instanceof \BackedEnum ? $this->status->value : $this->status,
            'priority' => $this->priority instanceof \BackedEnum ? $this->priority->value : $this->priority,
            'category_id' => $this->category_id,
            'customer_id' => $this->customer_id,
            'assigned_to' => $this->assigned_to,
            'category' => $this->whenLoaded('category', fn () => [
                'id' => $this->category->id,
                'name' => $this->category->name,
                'slug' => $this->category->slug ?? null,
            ]),
            'customer' => $this->whenLoaded('customer', fn () => new UserResource($this->customer)),
            'assignee' => $assigneeUser ? new UserResource($assigneeUser) : null,
            'assigne' => $assigneeUser ? new UserResource($assigneeUser) : null,
            'assigned_to_user' => $assigneeUser ? new UserResource($assigneeUser) : null,
            'assigned_agent' => $assigneeUser ? new UserResource($assigneeUser) : null,
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }
}

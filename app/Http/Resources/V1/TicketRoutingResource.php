<?php

declare(strict_types=1);

namespace App\Http\Resources\V1;

use App\Domain\Ticket\Routing\Rules\TicketRoutingDecision;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin TicketRoutingDecision
 */
class TicketRoutingResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'ticket' => new TicketResource($this->ticket),
            'priority' => $this->priority->value,
            'assigned_to' => $this->assignedTo,
            'category_id' => $this->categoryId,
            'matched_rule' => $this->matchedRule,
            'reason' => $this->reason,
            'tags' => $this->tags,
        ];
    }
}

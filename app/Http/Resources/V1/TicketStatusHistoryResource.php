<?php

declare(strict_types=1);

namespace App\Http\Resources\V1;

use App\Models\TicketStatusHistory;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin TicketStatusHistory
 */
class TicketStatusHistoryResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'ticket_id' => $this->ticket_id,
            'from_status' => $this->from_status?->value ?? (is_string($this->from_status) ? $this->from_status : null),
            'to_status' => $this->to_status?->value ?? (is_string($this->to_status) ? $this->to_status : null),
            'changed_by' => $this->changed_by,
            'reason' => $this->reason,
            'created_at' => $this->created_at?->toISOString(),
        ];
    }
}

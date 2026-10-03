<?php

declare(strict_types=1);

namespace App\Http\Resources\V1;

use App\Domain\Ticket\Models\TicketAttachment;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin TicketAttachment
 */
class TicketAttachmentResource extends JsonResource
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
            'user_id' => $this->user_id,
            'original_name' => $this->original_name,
            'file_size' => $this->file_size,
            'mime_type' => $this->mime_type,
            'disk' => $this->disk,
            'is_image' => $this->isImage(),
            'is_pdf' => $this->isPdf(),
            'download_url' => url("/api/v1/attachments/{$this->id}/download"),
            'view_url' => url("/api/v1/attachments/{$this->id}/view"),
            'created_at' => $this->created_at?->toISOString(),
        ];
    }
}

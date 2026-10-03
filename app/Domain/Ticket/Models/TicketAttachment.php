<?php

declare(strict_types=1);

namespace App\Domain\Ticket\Models;

use App\Domain\Identity\Models\User;
use Database\Factories\TicketAttachmentFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;

#[Fillable(
    'ticket_id',
    'user_id',
    'original_name',
    'file_path',
    'disk',
    'mime_type',
    'file_size'
)]
class TicketAttachment extends Model
{
    /** @use HasFactory<TicketAttachmentFactory> */
    use HasFactory;

    protected function casts(): array
    {
        return [
            'file_size' => 'integer',
        ];
    }

    public function ticket(): BelongsTo
    {
        return $this->belongsTo(Ticket::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function isImage(): bool
    {
        return in_array($this->mime_type, ['image/png', 'image/jpeg', 'image/jpg'], true);
    }

    public function isPdf(): bool
    {
        return $this->mime_type === 'application/pdf';
    }

    public function getStorageUrl(): ?string
    {
        try {
            $disk = Storage::disk($this->disk);
            if ($this->disk === 's3' && method_exists($disk, 'temporaryUrl')) {
                return $disk->temporaryUrl($this->file_path, now()->addMinutes(30));
            }

            return $disk->url($this->file_path);
        } catch (\Throwable) {
            return null;
        }
    }
}

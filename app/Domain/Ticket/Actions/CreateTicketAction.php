<?php

declare(strict_types=1);

namespace App\Domain\Ticket\Actions;

use App\Domain\Ticket\DTOs\CreateTicketData;
use App\Domain\Ticket\Enums\TicketStatus;
use App\Domain\Ticket\Events\TicketCreated;
use App\Domain\Ticket\Models\Ticket;
use App\Domain\Ticket\Models\TicketAttachment;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;

final class CreateTicketAction
{
    public function execute(
        CreateTicketData $createTicketData,
    ): Ticket {
        return DB::transaction(function () use ($createTicketData) {
            $ticket = Ticket::query()->create([
                'customer_id' => $createTicketData->customer_id,
                'category_id' => $createTicketData->category_id,
                'subject' => $createTicketData->subject,
                'description' => $createTicketData->description,
                'priority' => $createTicketData->priority,
                'status' => TicketStatus::Open,
            ]);

            // Link staged attachments
            if (! empty($createTicketData->attachment_ids)) {
                TicketAttachment::query()
                    ->whereIn('id', $createTicketData->attachment_ids)
                    ->where('user_id', $createTicketData->customer_id)
                    ->whereNull('ticket_id')
                    ->update(['ticket_id' => $ticket->id]);
            }

            // Handle direct multipart file uploads if any
            if (! empty($createTicketData->attachments)) {
                $disk = config('filesystems.default', 'public');
                foreach ($createTicketData->attachments as $file) {
                    if ($file instanceof UploadedFile) {
                        $path = $file->store('attachments/'.date('Y/m'), $disk);
                        TicketAttachment::query()->create([
                            'ticket_id' => $ticket->id,
                            'user_id' => $createTicketData->customer_id,
                            'original_name' => $file->getClientOriginalName(),
                            'file_path' => $path,
                            'disk' => $disk,
                            'mime_type' => $file->getMimeType() ?: 'application/octet-stream',
                            'file_size' => $file->getSize(),
                        ]);
                    }
                }
            }

            TicketCreated::dispatch($ticket);

            return $ticket;
        });
    }
}

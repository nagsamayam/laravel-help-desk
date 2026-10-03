<?php

declare(strict_types=1);

namespace App\Http\Requests\V1;

use App\Domain\Ticket\Enums\TicketPriority;
use App\Domain\Ticket\Models\Ticket;
use App\Domain\Ticket\Models\TicketAttachment;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Http\UploadedFile;
use Illuminate\Validation\Rule;

class CreateTicketRequest extends TicketRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user()?->can('create', Ticket::class) ?? false;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $userId = $this->user()?->id;

        return [
            'subject' => [
                'required',
                'string',
                'max:255',
            ],
            'description' => [
                'required',
                'string',
            ],
            'category_id' => [
                'required',
                'integer',
                Rule::exists('categories', 'id')
                    ->where(fn ($query) => $query->where('is_active', true)),
            ],
            'priority' => [
                'required',
                Rule::enum(TicketPriority::class),
            ],
            'attachment_ids' => [
                'sometimes',
                'array',
                'max:5',
            ],
            'attachment_ids.*' => [
                'integer',
                Rule::exists('ticket_attachments', 'id')
                    ->where(fn ($query) => $query->where('user_id', $userId)->whereNull('ticket_id')),
            ],
            'attachments' => [
                'sometimes',
                'array',
                'max:5',
            ],
            'attachments.*' => [
                'file',
                'mimes:pdf,png,jpg,jpeg',
                'max:5120',
            ],
        ];
    }

    /**
     * Get the "after" validation callbacks for the request.
     *
     * @return array<int, callable>
     */
    public function after(): array
    {
        return [
            ...parent::after(),
            function ($validator): void {
                $attachmentIds = (array) $this->input('attachment_ids', []);
                $files = (array) $this->file('attachments', []);

                $totalCount = count($attachmentIds) + count($files);
                if ($totalCount > 5) {
                    $validator->errors()->add('attachment_ids', 'You cannot attach more than 5 files in total.');

                    return;
                }

                $totalSize = 0;
                $maxTotalBytes = 25 * 1024 * 1024; // 25 MB
                $maxSingleBytes = 5 * 1024 * 1024; // 5 MB
                $allowedMimes = ['application/pdf', 'image/png', 'image/jpeg', 'image/jpg'];

                if (! empty($attachmentIds)) {
                    $userId = $this->user()?->id ?? auth('api')->id();
                    $attachments = TicketAttachment::query()
                        ->whereIn('id', $attachmentIds)
                        ->where('user_id', $userId)
                        ->get();

                    foreach ($attachments as $att) {
                        if ($att->file_size > $maxSingleBytes) {
                            $validator->errors()->add('attachment_ids', "File {$att->original_name} exceeds the 5MB size limit.");
                        }
                        if (! in_array($att->mime_type, $allowedMimes, true)) {
                            $validator->errors()->add('attachment_ids', "File {$att->original_name} has an invalid format. Allowed formats: pdf, png, jpeg.");
                        }
                        $totalSize += $att->file_size;
                    }
                }

                foreach ($files as $file) {
                    if ($file instanceof UploadedFile) {
                        $totalSize += $file->getSize();
                    }
                }

                if ($totalSize > $maxTotalBytes) {
                    $validator->errors()->add('attachment_ids', 'Total size of all attachments cannot exceed 25MB.');
                }
            },
        ];
    }
}

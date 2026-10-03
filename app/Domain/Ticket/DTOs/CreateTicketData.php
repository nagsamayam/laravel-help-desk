<?php

declare(strict_types=1);

namespace App\Domain\Ticket\DTOs;

use App\Domain\Ticket\Enums\TicketPriority;
use Illuminate\Http\UploadedFile;
use Spatie\LaravelData\Data;

final class CreateTicketData extends Data
{
    /**
     * @param  array<int>  $attachment_ids
     * @param  array<UploadedFile>  $attachments
     */
    public function __construct(
        public string $subject,
        public string $description,
        public int $category_id,
        public TicketPriority $priority,
        public int $customer_id,
        public array $attachment_ids = [],
        public array $attachments = [],
    ) {}
}

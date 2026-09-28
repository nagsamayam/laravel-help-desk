<?php

declare(strict_types=1);

namespace App\DTOs;

use App\Enums\TicketPriority;
use Spatie\LaravelData\Data;

class CreateTicketData extends Data
{
    /**
     * Create a new class instance.
     */
    public function __construct(
        public string $subject,
        public string $description,
        public int $category_id,
        public TicketPriority $priority,
        public int $customer_id,
    ) {
        //
    }
}

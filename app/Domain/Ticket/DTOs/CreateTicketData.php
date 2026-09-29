<?php

declare(strict_types=1);

namespace App\Domain\Ticket\DTOs;

use App\Domain\Ticket\Enums\TicketPriority;
use Spatie\LaravelData\Data;

final class CreateTicketData extends Data
{
    public function __construct(
        public string $subject,
        public string $description,
        public int $category_id,
        public TicketPriority $priority,
        public int $customer_id,
    ) {}
}

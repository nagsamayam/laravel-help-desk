<?php

declare(strict_types=1);

namespace App\Http\Requests\V1;

use App\Enums\TicketPriority;
use App\Enums\TicketStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class FilterTicketsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'status' => ['nullable', Rule::enum(TicketStatus::class)],
            'priority' => ['nullable', Rule::enum(TicketPriority::class)],
            'open' => ['nullable', 'boolean'],
            'urgent' => ['nullable', 'boolean'],
            'unassigned' => ['nullable', 'boolean'],
            'assigned_to' => ['nullable', 'integer', 'exists:users,id'],
            'customer_id' => ['nullable', 'integer', 'exists:users,id'],
            'overdue' => ['nullable', 'boolean'],
            'overdue_days' => ['nullable', 'integer', 'min:1'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ];
    }
}

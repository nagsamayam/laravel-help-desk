<?php

declare(strict_types=1);

namespace App\Http\Requests\V1;

use App\Domain\Ticket\Enums\TicketStatus;
use App\Domain\Ticket\Models\Ticket;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class TransitionTicketRequest extends FormRequest
{
    public function authorize(): bool
    {
        $ticket = $this->route('ticket');
        if (is_numeric($ticket)) {
            $ticket = Ticket::query()->find($ticket);
        }

        return $ticket !== null && ($this->user()?->can('transition', $ticket) ?? false);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'status' => ['required', Rule::enum(TicketStatus::class)],
            'reason' => ['nullable', 'string', 'max:500'],
        ];
    }
}

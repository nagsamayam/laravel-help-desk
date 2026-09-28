<?php

declare(strict_types=1);

namespace App\Http\Requests\V1;

use App\Models\Ticket;
use Illuminate\Foundation\Http\FormRequest;

class CreateTicketMessageRequest extends FormRequest
{
    public function authorize(): bool
    {
        $ticket = $this->route('ticket');
        if (is_numeric($ticket)) {
            $ticket = Ticket::query()->find($ticket);
        }

        if ($ticket === null) {
            return false;
        }

        if ($this->boolean('is_internal')) {
            return $this->user()?->can('addInternalNote', $ticket) ?? false;
        }

        return $this->user()?->can('addMessage', $ticket) ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'message' => ['required', 'string', 'min:1', 'max:5000'],
            'is_internal' => ['nullable', 'boolean'],
        ];
    }
}

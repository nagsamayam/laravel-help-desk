<?php

declare(strict_types=1);

namespace App\Http\Requests\V1;

use App\Domain\Ticket\Models\Ticket;
use Illuminate\Foundation\Http\FormRequest;

class RouteTicketRequest extends FormRequest
{
    public function authorize(): bool
    {
        $ticket = $this->route('ticket');
        if (is_numeric($ticket)) {
            $ticket = Ticket::query()->find($ticket);
        }

        return $ticket !== null && ($this->user()?->can('route', $ticket) ?? false);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'persist' => ['nullable', 'boolean'],
        ];
    }
}

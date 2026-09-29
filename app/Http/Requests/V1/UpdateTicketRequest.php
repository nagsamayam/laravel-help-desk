<?php

declare(strict_types=1);

namespace App\Http\Requests\V1;

use App\Domain\Ticket\Enums\TicketPriority;
use App\Domain\Ticket\Models\Ticket;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Validation\Rule;

class UpdateTicketRequest extends TicketRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        $ticket = $this->route('ticket');
        if (is_numeric($ticket)) {
            $ticket = Ticket::query()->find($ticket);
        }

        return $ticket !== null && ($this->user()?->can('update', $ticket) ?? false);
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'category_id' => [
                'required',
                'integer',
                Rule::exists('categories', 'id')
                    ->where(fn ($query) => $query->where('is_active', true)),
            ],
            'subject' => [
                'required',
                'string',
                'max:255',
            ],
            'description' => [
                'required',
                'string',
            ],
            'priority' => [
                'required',
                Rule::enum(TicketPriority::class),
            ],
        ];
    }
}

<?php

declare(strict_types=1);

namespace App\Http\Requests\V1;

use App\Domain\Ticket\Models\Ticket;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class AssignTicketRequest extends FormRequest
{
    public function authorize(): bool
    {
        $ticket = $this->route('ticket');
        if (is_numeric($ticket)) {
            $ticket = Ticket::query()->find($ticket);
        }

        return $ticket !== null && ($this->user()?->can('assign', $ticket) ?? false);
    }

    protected function prepareForValidation(): void
    {
        if ($this->has('assigned_to') && ! $this->has('agent_id')) {
            $this->merge([
                'agent_id' => $this->input('assigned_to'),
            ]);
        }
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'agent_id' => [
                'nullable',
                'integer',
                Rule::exists('users', 'id'),
                'required_without:strategy',
            ],
            'assigned_to' => [
                'nullable',
                'integer',
                Rule::exists('users', 'id'),
            ],
            'strategy' => [
                'nullable',
                'string',
                Rule::in(['round_robin', 'least_busy', 'skill_based']),
                'required_without_all:agent_id,assigned_to',
            ],
        ];
    }
}

<?php

declare(strict_types=1);

namespace App\Http\Requests\V1;

use App\Models\Ticket;
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
            'strategy' => [
                'nullable',
                'string',
                Rule::in(['round_robin', 'least_busy', 'skill_based']),
                'required_without:agent_id',
            ],
        ];
    }
}

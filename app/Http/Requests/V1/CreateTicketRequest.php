<?php

declare(strict_types=1);

namespace App\Http\Requests\V1;

use App\Enums\Role;
use App\Enums\TicketPriority;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Validation\Rule;

class CreateTicketRequest extends TicketRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user()?->hasRole(Role::Customer) ?? false;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'subject' => [
                'required',
                'string',
                'max:255',
            ],
            'description' => [
                'required',
                'string',
            ],
            'category_id' => [
                'required',
                'integer',
                Rule::exists('categories', 'id')
                    ->where(fn ($query) => $query->where('is_active', true)),
            ],
            'priority' => [
                'required',
                Rule::enum(TicketPriority::class),
            ],
        ];
    }
}

<?php

declare(strict_types=1);

namespace App\Http\Requests\V1;

use App\Models\AuditLog;
use Illuminate\Foundation\Http\FormRequest;

class FilterAuditLogsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('viewAny', AuditLog::class) ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'user_id' => ['nullable', 'integer', 'exists:users,id'],
            'action' => ['nullable', 'string', 'max:50'],
            'auditable_type' => ['nullable', 'string', 'max:120'],
            'auditable_id' => ['nullable', 'string', 'max:64'],
            'date_from' => ['nullable', 'date'],
            'date_to' => ['nullable', 'date', 'after_or_equal:date_from'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ];
    }
}

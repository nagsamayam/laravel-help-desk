<?php

declare(strict_types=1);

namespace App\Infrastructure\Idempotency\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\MassPrunable;
use Illuminate\Database\Eloquent\Model;

#[Fillable([
    'scope_type',
    'scope_id',
    'operation',
    'key_hash',
    'request_hash',
    'response_status',
    'response_body',
    'response_headers',
    'resource_type',
    'resource_id',
    'expires_at',
    'completed_at',
])]
class IdempotencyKey extends Model
{
    use MassPrunable;

    public const CREATED_AT = 'created_at';

    public const UPDATED_AT = null;

    protected function casts(): array
    {
        return [
            'response_body' => 'array',
            'response_headers' => 'array',
            'expires_at' => 'immutable_datetime',
            'completed_at' => 'immutable_datetime',
        ];
    }

    public function prunable(): Builder
    {
        return static::query()->where('expires_at', '<=', now());
    }
}

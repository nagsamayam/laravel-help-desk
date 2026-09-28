<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Prunable;

#[Fillable([
    'scope_type',
    'scope_id',
    'operation',
    'key_hash',
    'request_hash',
    'response_status',
    'response_body',
    'resource_type',
    'resource_id',
    'expires_at',
    'completed_at',
])]
class IdempotencyKey extends Model
{
    use Prunable;

    protected function casts(): array
    {
        return [
            'response_body' => 'array',
            'expires_at' => 'datetime',
            'completed_at' => 'datetime',
        ];
    }

    public function prunable(): Builder
    {
        return static::query()->where('expires_at', '<=', now());
    }
}

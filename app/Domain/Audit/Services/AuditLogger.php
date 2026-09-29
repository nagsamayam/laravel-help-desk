<?php

declare(strict_types=1);

namespace App\Domain\Audit\Services;

use App\Domain\Audit\Models\AuditLog;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Request;

final class AuditLogger
{
    /**
     * @param  array<string, mixed>|null  $oldValues
     * @param  array<string, mixed>|null  $newValues
     */
    public function log(
        string $action,
        Model $auditable,
        ?array $oldValues = null,
        ?array $newValues = null,
        ?int $userId = null,
        ?string $ipAddress = null,
        ?string $userAgent = null,
    ): AuditLog {
        $resolvedUserId = $userId ?? Auth::id();
        $resolvedIp = $ipAddress ?? (app()->bound('request') ? Request::ip() : null);
        $resolvedUserAgent = $userAgent ?? (app()->bound('request') ? Request::userAgent() : null);

        return AuditLog::create([
            'user_id' => $resolvedUserId,
            'action' => $action,
            'auditable_type' => $auditable->getMorphClass(),
            'auditable_id' => (string) $auditable->getKey(),
            'old_values' => $this->sanitizeValues($oldValues),
            'new_values' => $this->sanitizeValues($newValues),
            'ip_address' => $resolvedIp,
            'user_agent' => $resolvedUserAgent,
            'created_at' => now(),
        ]);
    }

    /**
     * @param  array<string, mixed>|null  $values
     * @return array<string, mixed>|null
     */
    private function sanitizeValues(?array $values): ?array
    {
        if ($values === null) {
            return null;
        }

        $sensitive = ['password', 'remember_token', 'token', 'secret'];

        foreach ($sensitive as $key) {
            if (array_key_exists($key, $values)) {
                $values[$key] = '********';
            }
        }

        return $values;
    }
}

<?php

declare(strict_types=1);

namespace App\Policies;

use App\Enums\Role;
use App\Models\AuditLog;
use App\Models\User;

final class AuditLogPolicy
{
    /**
     * Perform pre-authorization checks.
     */
    public function before(User $user, string $ability): ?bool
    {
        if ($user->hasRole(Role::Admin)) {
            return true;
        }

        return null;
    }

    /**
     * Determine whether the user can view any audit logs.
     */
    public function viewAny(User $user): bool
    {
        return $user->hasRole(Role::Agent);
    }

    /**
     * Determine whether the user can view the specific audit log.
     */
    public function view(User $user, AuditLog $auditLog): bool
    {
        return $user->hasRole(Role::Agent);
    }
}

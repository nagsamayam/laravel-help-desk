<?php

declare(strict_types=1);

namespace App\Policies;

use App\Enums\Role;
use App\Models\User;

final class UserPolicy
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
     * Determine whether the user can view any users.
     */
    public function viewAny(User $user): bool
    {
        return $user->hasRole(Role::Agent);
    }

    /**
     * Determine whether the user can view the specific user.
     */
    public function view(User $user, User $model): bool
    {
        return $user->id === $model->id || $user->hasRole(Role::Agent);
    }

    /**
     * Determine whether the user can update the specific user.
     */
    public function update(User $user, User $model): bool
    {
        return $user->id === $model->id;
    }

    /**
     * Determine whether the user can delete the specific user.
     */
    public function delete(User $user, User $model): bool
    {
        return false;
    }
}

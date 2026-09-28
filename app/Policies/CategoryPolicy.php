<?php

declare(strict_types=1);

namespace App\Policies;

use App\Enums\Role;
use App\Models\Category;
use App\Models\User;

final class CategoryPolicy
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
     * Determine whether the user can view any categories.
     */
    public function viewAny(User $user): bool
    {
        return true;
    }

    /**
     * Determine whether the user can view the category.
     */
    public function view(User $user, Category $category): bool
    {
        return true;
    }

    /**
     * Determine whether the user can create categories.
     */
    public function create(User $user): bool
    {
        return false;
    }

    /**
     * Determine whether the user can update the category.
     */
    public function update(User $user, Category $category): bool
    {
        return false;
    }

    /**
     * Determine whether the user can delete the category.
     */
    public function delete(User $user, Category $category): bool
    {
        return false;
    }
}

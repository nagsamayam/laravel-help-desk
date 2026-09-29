<?php

declare(strict_types=1);

namespace App\Domain\Identity\Providers;

use App\Domain\Identity\Models\User;
use App\Domain\Identity\Policies\UserPolicy;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;

final class IdentityServiceProvider extends ServiceProvider
{
    /**
     * Bootstrap domain services and policies.
     */
    public function boot(): void
    {
        Gate::policy(User::class, UserPolicy::class);
    }
}

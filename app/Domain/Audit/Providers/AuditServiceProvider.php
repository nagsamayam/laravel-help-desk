<?php

declare(strict_types=1);

namespace App\Domain\Audit\Providers;

use App\Domain\Audit\Models\AuditLog;
use App\Domain\Audit\Policies\AuditLogPolicy;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;

final class AuditServiceProvider extends ServiceProvider
{
    /**
     * Bootstrap domain services and policies.
     */
    public function boot(): void
    {
        Gate::policy(AuditLog::class, AuditLogPolicy::class);
    }
}

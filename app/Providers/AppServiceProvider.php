<?php

declare(strict_types=1);

namespace App\Providers;

use App\Enums\Role;
use App\Models\AuditLog;
use App\Models\Category;
use App\Models\Ticket;
use App\Models\TicketMessage;
use App\Models\TicketStatusHistory;
use App\Models\User;
use App\Policies\AuditLogPolicy;
use App\Policies\CategoryPolicy;
use App\Policies\TicketMessagePolicy;
use App\Policies\TicketPolicy;
use App\Policies\TicketStatusHistoryPolicy;
use App\Policies\UserPolicy;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Application;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Rules\Password;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        /** @var Application $app */
        $app = $this->app;

        $this->configurePasswordRules();

        $this->configurePolicies();

        Model::shouldBeStrict(! $app->isProduction());

        $this->configureRateLimiting();

        $this->buildRouteMacros();
    }

    private function configurePolicies(): void
    {
        Gate::policy(Ticket::class, TicketPolicy::class);
        Gate::policy(TicketMessage::class, TicketMessagePolicy::class);
        Gate::policy(TicketStatusHistory::class, TicketStatusHistoryPolicy::class);
        Gate::policy(AuditLog::class, AuditLogPolicy::class);
        Gate::policy(Category::class, CategoryPolicy::class);
        Gate::policy(User::class, UserPolicy::class);
    }

    private function configureRateLimiting(): void
    {
        RateLimiter::for('auth-register', function (Request $request) {
            /** @var int $limit */
            $limit = config('auth.rate_limiting.register', 3);

            return Limit::perMinute($limit)->by($request->ip() ?: 'unknown');
        });

        RateLimiter::for('auth-login', function (Request $request) {
            /** @var int $limit */
            $limit = config('auth.rate_limiting.login', 3);

            return Limit::perMinute($limit)->by($request->ip() ?: 'unknown');
        });

        RateLimiter::for('auth-refresh', function (Request $request) {
            /** @var int $limit */
            $limit = config('auth.rate_limiting.refresh', 3);

            return Limit::perMinute($limit)->by($request->ip() ?: 'unknown');
        });
    }

    private function configurePasswordRules()
    {
        /** @var Application $app */
        $app = $this->app;

        Password::defaults(function () {
            $rule = Password::min(8);

            /** @var Application $app */
            $app = $this->app;

            return $app->isProduction()
                ? $rule
                    ->max(64)
                    ->mixedCase()
                    ->numbers()
                    ->symbols()
                    ->uncompromised()
                : $rule;
        });
    }

    private function buildRouteMacros()
    {
        Route::macro('roles', function (Role ...$roles) {
            $roleValues = array_map(fn ($role) => $role->value, $roles);
            $this->middleware(Role::class.':'.implode(',', $roleValues));
        });
    }
}

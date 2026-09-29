<?php

declare(strict_types=1);

namespace App\Providers;

use App\Domain\Identity\Enums\Role;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Application;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Rules\Password;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void {}

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        /** @var Application $app */
        $app = $this->app;

        Factory::guessFactoryNamesUsing(
            fn (string $modelName) => 'Database\\Factories\\'.class_basename($modelName).'Factory'
        );

        $this->configurePasswordRules();

        Model::shouldBeStrict(! $app->isProduction());

        $this->configureRateLimiting();

        $this->buildRouteMacros();
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

    private function configurePasswordRules(): void
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

    private function buildRouteMacros(): void
    {
        Route::macro('roles', function (Role ...$roles) {
            $roleValues = array_map(fn ($role) => $role->value, $roles);
            $this->middleware(Role::class.':'.implode(',', $roleValues));
        });
    }
}

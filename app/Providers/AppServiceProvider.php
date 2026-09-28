<?php

declare(strict_types=1);

namespace App\Providers;

use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Application;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
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

        Model::shouldBeStrict(! $app->isProduction());

        $this->configureRateLimiting();
    }

    protected function configureRateLimiting(): void
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
}

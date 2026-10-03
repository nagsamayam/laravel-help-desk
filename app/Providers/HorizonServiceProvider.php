<?php

namespace App\Providers;

use App\Domain\Identity\Enums\Role;
use Illuminate\Support\Facades\Gate;
use Laravel\Horizon\Horizon;
use Laravel\Horizon\HorizonApplicationServiceProvider;
use PHPOpenSourceSaver\JWTAuth\Facades\JWTAuth;

class HorizonServiceProvider extends HorizonApplicationServiceProvider
{
    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        parent::boot();

        // Horizon::routeSmsNotificationsTo('15556667777');
        // Horizon::routeMailNotificationsTo('example@example.com');
        // Horizon::routeSlackNotificationsTo('slack-webhook-url', '#channel');
    }

    /**
     * Configure the Horizon authorization services.
     */
    protected function authorization(): void
    {
        $this->gate();

        Horizon::auth(function ($request) {
            $user = $request->user('api') ?? $request->user('web') ?? $request->user();

            if (! $user) {
                $token = $request->bearerToken() ?? $request->query('token');

                if ($token) {
                    try {
                        $user = JWTAuth::setToken($token)->toUser();

                        if ($user && $request->hasSession()) {
                            auth('web')->login($user);
                        }
                    } catch (\Throwable) {
                        $user = null;
                    }
                }
            }

            if ($user) {
                return Gate::forUser($user)->allows('viewHorizon');
            }

            return app()->environment('local');
        });
    }

    /**
     * Register the Horizon gate.
     *
     * This gate determines who can access Horizon.
     */
    protected function gate(): void
    {
        Gate::define('viewHorizon', function ($user = null) {
            if (! $user) {
                return false;
            }

            if (method_exists($user, 'hasRole') && $user->hasRole(Role::Admin)) {
                return true;
            }

            return false;
        });
    }
}

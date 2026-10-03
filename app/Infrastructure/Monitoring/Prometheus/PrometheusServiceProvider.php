<?php

declare(strict_types=1);

namespace App\Infrastructure\Monitoring\Prometheus;

use Illuminate\Support\ServiceProvider;
use Prometheus\CollectorRegistry;
use Prometheus\Storage\Redis;

final class PrometheusServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(CollectorRegistry::class, function (): CollectorRegistry {
            Redis::setPrefix((string) config('prometheus.redis_prefix', 'HELPDESK_PROMETHEUS_'));

            return new CollectorRegistry(new Redis([
                'host' => (string) config('prometheus.redis_host', '127.0.0.1'),
                'port' => (int) config('prometheus.redis_port', 6379),
                'timeout' => (float) config('prometheus.redis_timeout', 0.1),
                'read_timeout' => (float) config('prometheus.redis_read_timeout', 1.0),
                'password' => config('prometheus.redis_password'),
                'persistent_connections' => (bool) config('prometheus.redis_persistent', false),
            ]));
        });

        $this->app->singleton(PrometheusMetrics::class);
    }
}

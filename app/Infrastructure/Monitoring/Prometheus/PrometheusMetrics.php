<?php

declare(strict_types=1);

namespace App\Infrastructure\Monitoring\Prometheus;

use Prometheus\CollectorRegistry;
use Prometheus\RenderTextFormat;

final class PrometheusMetrics
{
    private const NAMESPACE = 'helpdesk';

    /**
     * @var float[]
     */
    private const HTTP_LATENCY_BUCKETS = [
        0.005,
        0.01,
        0.025,
        0.05,
        0.1,
        0.25,
        0.5,
        0.75,
        1.0,
        2.5,
        5.0,
        10.0,
    ];

    public function __construct(
        private readonly CollectorRegistry $registry,
    ) {}

    public function recordHttpRequest(
        string $method,
        string $route,
        int $statusCode,
        float $durationSeconds,
    ): void {
        $labels = [$method, $route, (string) $statusCode];

        $this->registry
            ->getOrRegisterCounter(
                self::NAMESPACE,
                'http_requests_total',
                'Total number of HTTP requests handled by Laravel.',
                ['method', 'route', 'status'],
            )
            ->inc($labels);

        $this->registry
            ->getOrRegisterHistogram(
                self::NAMESPACE,
                'http_request_duration_seconds',
                'HTTP request duration in seconds.',
                ['method', 'route', 'status'],
                self::HTTP_LATENCY_BUCKETS,
            )
            ->observe($durationSeconds, $labels);
    }

    public function incrementInFlight(string $method, string $route): void
    {
        $this->registry
            ->getOrRegisterGauge(
                self::NAMESPACE,
                'http_requests_in_flight',
                'Current number of HTTP requests being processed by Laravel.',
                ['method', 'route'],
            )
            ->inc([$method, $route]);
    }

    public function decrementInFlight(string $method, string $route): void
    {
        $this->registry
            ->getOrRegisterGauge(
                self::NAMESPACE,
                'http_requests_in_flight',
                'Current number of HTTP requests being processed by Laravel.',
                ['method', 'route'],
            )
            ->dec([$method, $route]);
    }

    public function render(): string
    {
        return (new RenderTextFormat)->render(
            $this->registry->getMetricFamilySamples()
        );
    }
}

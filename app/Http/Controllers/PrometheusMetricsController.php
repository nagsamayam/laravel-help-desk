<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Infrastructure\Monitoring\Prometheus\PrometheusMetrics;
use Illuminate\Http\Response;

final class PrometheusMetricsController extends Controller
{
    public function __invoke(PrometheusMetrics $metrics): Response
    {
        return response(
            $metrics->render(),
            Response::HTTP_OK,
            ['Content-Type' => 'text/plain; version=0.0.4; charset=utf-8'],
        );
    }
}

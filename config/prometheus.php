<?php

declare(strict_types=1);

return [
    'redis_host' => env('PROMETHEUS_REDIS_HOST', env('REDIS_HOST', '127.0.0.1')),
    'redis_port' => env('PROMETHEUS_REDIS_PORT', env('REDIS_PORT', 6379)),
    'redis_password' => env('PROMETHEUS_REDIS_PASSWORD', env('REDIS_PASSWORD')),
    'redis_timeout' => env('PROMETHEUS_REDIS_TIMEOUT', 0.1),
    'redis_read_timeout' => env('PROMETHEUS_REDIS_READ_TIMEOUT', 1.0),
    'redis_persistent' => env('PROMETHEUS_REDIS_PERSISTENT', false),
    'redis_prefix' => env('PROMETHEUS_REDIS_PREFIX', 'HELPDESK_PROMETHEUS_'),
];

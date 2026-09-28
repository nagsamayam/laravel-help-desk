<?php

declare(strict_types=1);

return [
    'ttl_seconds' => (int) env('IDEMPOTENCY_TTL_SECONDS', 86_400),
];

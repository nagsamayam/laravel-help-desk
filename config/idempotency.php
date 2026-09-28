<?php

return [
    'ttl_seconds' => (int) env('IDEMPOTENCY_TTL_SECONDS', 86_400),
];

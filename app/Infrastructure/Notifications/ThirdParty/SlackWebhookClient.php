<?php

declare(strict_types=1);

namespace App\Infrastructure\Notifications\ThirdParty;

/**
 * Incompatible Slack Webhook / Bot Client representation.
 * Represents external 3rd-party vendor API/SDK.
 */
class SlackWebhookClient
{
    public function __construct(
        private readonly string $webhookUrl,
    ) {}

    /**
     * Incompatible vendor method signature: expects structured block/attachment payload array.
     *
     * @param  array<string, mixed>  $payload
     */
    public function postJson(array $payload): bool
    {
        // Simulated HTTP POST to Slack Webhook endpoint
        return ! empty($payload);
    }

    public function getWebhookUrl(): string
    {
        return $this->webhookUrl;
    }
}

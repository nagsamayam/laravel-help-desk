<?php

declare(strict_types=1);

namespace App\Infrastructure\Notifications\ThirdParty;

/**
 * Incompatible SendGrid Email SDK representation.
 * Represents external 3rd-party vendor API/SDK.
 */
class SendGridClient
{
    public function __construct(
        private readonly string $apiKey,
    ) {}

    /**
     * Incompatible vendor method signature: separate to/subject/body arguments + HTML flag.
     *
     * @param  array<string, string>  $customHeaders
     * @return array{status_code: int, x_message_id: string, timestamp: int}
     */
    public function sendMail(string $toEmail, string $subject, string $bodyText, bool $isHtml = false, array $customHeaders = []): array
    {
        // Simulated HTTP call to SendGrid API
        return [
            'status_code' => 202,
            'x_message_id' => 'sg_msg_'.bin2hex(random_bytes(8)),
            'timestamp' => time(),
        ];
    }

    public function getApiKey(): string
    {
        return $this->apiKey;
    }
}

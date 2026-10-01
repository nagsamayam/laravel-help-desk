<?php

declare(strict_types=1);

namespace App\Infrastructure\Notifications\ThirdParty;

/**
 * Incompatible Twilio SMS Client representation.
 * Represents external 3rd-party vendor API/SDK.
 */
class TwilioSmsClient
{
    public function __construct(
        private readonly string $accountSid,
        private readonly string $authToken,
        private readonly string $fromNumber,
    ) {}

    /**
     * Incompatible vendor method signature: parameters passed as destination string and options array.
     * Returns an SDK object response.
     *
     * @param  array<string, mixed>  $options
     */
    public function createMessage(string $destinationPhoneNumber, array $options): object
    {
        // Simulated Twilio SDK message response object
        return (object) [
            'sid' => 'SM'.bin2hex(random_bytes(16)),
            'status' => 'queued',
            'to' => $destinationPhoneNumber,
            'from' => $this->fromNumber,
            'body' => $options['body'] ?? '',
            'date_created' => date('Y-m-d H:i:s'),
        ];
    }

    public function getAccountSid(): string
    {
        return $this->accountSid;
    }

    public function getFromNumber(): string
    {
        return $this->fromNumber;
    }
}

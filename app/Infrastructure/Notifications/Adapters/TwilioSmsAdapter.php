<?php

declare(strict_types=1);

namespace App\Infrastructure\Notifications\Adapters;

use App\Domain\Identity\Models\User;
use App\Infrastructure\Notifications\NotificationResult;
use App\Infrastructure\Notifications\NotificationSenderInterface;
use App\Infrastructure\Notifications\ThirdParty\TwilioSmsClient;
use Throwable;

final class TwilioSmsAdapter implements NotificationSenderInterface
{
    public function __construct(
        private readonly TwilioSmsClient $client,
    ) {}

    /**
     * @param  array<string, mixed>  $context
     */
    public function send(User $recipient, string $title, string $content, array $context = []): NotificationResult
    {
        // Resolve destination phone number from context or recipient attributes
        $phoneNumber = $context['phone_number'] ?? $recipient->getAttribute('phone') ?? null;

        if (empty($phoneNumber)) {
            return NotificationResult::failure(
                recipientEmail: $recipient->email,
                channel: 'sms',
                metadata: array_merge($context, [
                    'provider' => 'twilio',
                    'error' => 'Recipient phone number is missing',
                ]),
            );
        }

        try {
            // Translate application notification into concise SMS format
            $smsBody = "{$title}: {$content}";

            $response = $this->client->createMessage(
                destinationPhoneNumber: (string) $phoneNumber,
                options: [
                    'body' => $smsBody,
                ],
            );

            return NotificationResult::success(
                recipientEmail: $recipient->email,
                channel: 'sms',
                messageId: $response->sid ?? null,
                metadata: array_merge($context, [
                    'provider' => 'twilio',
                    'destination_phone' => (string) $phoneNumber,
                    'status' => $response->status ?? 'queued',
                ]),
            );
        } catch (Throwable $e) {
            return NotificationResult::failure(
                recipientEmail: $recipient->email,
                channel: 'sms',
                metadata: array_merge($context, [
                    'provider' => 'twilio',
                    'destination_phone' => (string) $phoneNumber,
                    'error' => $e->getMessage(),
                ]),
            );
        }
    }

    public function getClient(): TwilioSmsClient
    {
        return $this->client;
    }
}

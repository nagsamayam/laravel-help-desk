<?php

declare(strict_types=1);

namespace App\Infrastructure\Notifications\Adapters;

use App\Domain\Identity\Models\User;
use App\Infrastructure\Notifications\NotificationResult;
use App\Infrastructure\Notifications\NotificationSenderInterface;
use App\Infrastructure\Notifications\ThirdParty\SendGridClient;
use Throwable;

final class SendGridEmailAdapter implements NotificationSenderInterface
{
    public function __construct(
        private readonly SendGridClient $client,
    ) {}

    /**
     * @param  array<string, mixed>  $context
     */
    public function send(User $recipient, string $title, string $content, array $context = []): NotificationResult
    {
        try {
            $isHtml = (bool) ($context['is_html'] ?? false);
            $customHeaders = isset($context['headers']) && is_array($context['headers'])
                ? $context['headers']
                : [];

            // Translate application domain parameters to SendGrid's expected format
            $response = $this->client->sendMail(
                toEmail: $recipient->email,
                subject: $title,
                bodyText: $content,
                isHtml: $isHtml,
                customHeaders: $customHeaders,
            );

            $statusCode = $response['status_code'] ?? 0;

            if ($statusCode >= 200 && $statusCode < 300) {
                return NotificationResult::success(
                    recipientEmail: $recipient->email,
                    channel: 'email',
                    messageId: $response['x_message_id'] ?? null,
                    metadata: array_merge($context, [
                        'provider' => 'sendgrid',
                        'status_code' => $statusCode,
                    ]),
                );
            }

            return NotificationResult::failure(
                recipientEmail: $recipient->email,
                channel: 'email',
                metadata: array_merge($context, [
                    'provider' => 'sendgrid',
                    'status_code' => $statusCode,
                ]),
            );
        } catch (Throwable $e) {
            return NotificationResult::failure(
                recipientEmail: $recipient->email,
                channel: 'email',
                metadata: array_merge($context, [
                    'provider' => 'sendgrid',
                    'error' => $e->getMessage(),
                ]),
            );
        }
    }

    public function getClient(): SendGridClient
    {
        return $this->client;
    }
}

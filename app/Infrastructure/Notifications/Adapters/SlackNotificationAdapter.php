<?php

declare(strict_types=1);

namespace App\Infrastructure\Notifications\Adapters;

use App\Domain\Identity\Models\User;
use App\Infrastructure\Notifications\NotificationResult;
use App\Infrastructure\Notifications\NotificationSenderInterface;
use App\Infrastructure\Notifications\ThirdParty\SlackWebhookClient;
use Illuminate\Support\Str;
use Throwable;

final class SlackNotificationAdapter implements NotificationSenderInterface
{
    public function __construct(
        private readonly SlackWebhookClient $client,
        private readonly ?string $defaultChannel = null,
    ) {}

    /**
     * @param  array<string, mixed>  $context
     */
    public function send(User $recipient, string $title, string $content, array $context = []): NotificationResult
    {
        try {
            // Translate generic application domain notification into Slack Block Kit payload
            $slackChannel = $context['slack_channel'] ?? $this->defaultChannel;

            $payload = [
                'text' => "*{$title}*\n{$content}",
                'blocks' => [
                    [
                        'type' => 'header',
                        'text' => [
                            'type' => 'plain_text',
                            'text' => $title,
                        ],
                    ],
                    [
                        'type' => 'section',
                        'text' => [
                            'type' => 'mrkdwn',
                            'text' => $content,
                        ],
                    ],
                    [
                        'type' => 'context',
                        'elements' => [
                            [
                                'type' => 'mrkdwn',
                                'text' => "Recipient: *{$recipient->full_name}* ({$recipient->email})",
                            ],
                        ],
                    ],
                ],
            ];

            if ($slackChannel !== null) {
                $payload['channel'] = $slackChannel;
            }

            $success = $this->client->postJson($payload);

            if ($success) {
                return NotificationResult::success(
                    recipientEmail: $recipient->email,
                    channel: 'slack',
                    messageId: (string) Str::uuid(),
                    metadata: array_merge($context, [
                        'provider' => 'slack',
                        'slack_channel' => $slackChannel,
                        'payload' => $payload,
                    ]),
                );
            }

            return NotificationResult::failure(
                recipientEmail: $recipient->email,
                channel: 'slack',
                metadata: array_merge($context, [
                    'provider' => 'slack',
                    'error' => 'Webhook returned unsuccessful response',
                ]),
            );
        } catch (Throwable $e) {
            return NotificationResult::failure(
                recipientEmail: $recipient->email,
                channel: 'slack',
                metadata: array_merge($context, [
                    'provider' => 'slack',
                    'error' => $e->getMessage(),
                ]),
            );
        }
    }

    public function getClient(): SlackWebhookClient
    {
        return $this->client;
    }
}

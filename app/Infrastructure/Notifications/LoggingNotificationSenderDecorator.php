<?php

declare(strict_types=1);

namespace App\Infrastructure\Notifications;

use App\Domain\Identity\Models\User;
use Illuminate\Support\Facades\Log;
use Psr\Log\LoggerInterface;

final class LoggingNotificationSenderDecorator implements NotificationSenderInterface
{
    public function __construct(
        private readonly NotificationSenderInterface $inner,
        private readonly ?LoggerInterface $logger = null,
    ) {}

    /**
     * @param  array<string, mixed>  $context
     */
    public function send(User $recipient, string $title, string $content, array $context = []): NotificationResult
    {
        $this->logInfo('Sending notification', [
            'recipient' => $recipient->email,
            'title' => $title,
            'context' => $context,
        ]);

        $result = $this->inner->send($recipient, $title, $content, $context);

        $this->logInfo('Notification sent', [
            'recipient' => $recipient->email,
            'successful' => $result->successful,
            'message_id' => $result->messageId,
        ]);

        return $result;
    }

    /**
     * @param  array<string, mixed>  $context
     */
    private function logInfo(string $message, array $context): void
    {
        if ($this->logger !== null) {
            $this->logger->info($message, $context);
        } else {
            Log::info($message, $context);
        }
    }
}

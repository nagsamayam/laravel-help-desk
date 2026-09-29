<?php

declare(strict_types=1);

namespace App\Infrastructure\Notifications;

use App\Domain\Identity\Models\User;

final class RetryNotificationSenderDecorator implements NotificationSenderInterface
{
    public function __construct(
        private readonly NotificationSenderInterface $inner,
        private readonly int $maxAttempts = 3,
        private readonly int $delayMilliseconds = 0,
    ) {}

    /**
     * @param  array<string, mixed>  $context
     */
    public function send(User $recipient, string $title, string $content, array $context = []): NotificationResult
    {
        $attempt = 0;
        $lastException = null;

        while ($attempt < $this->maxAttempts) {
            $attempt++;

            try {
                $result = $this->inner->send($recipient, $title, $content, array_merge($context, [
                    'attempt' => $attempt,
                    'max_attempts' => $this->maxAttempts,
                ]));

                if ($result->successful || $attempt >= $this->maxAttempts) {
                    return new NotificationResult(
                        successful: $result->successful,
                        recipientEmail: $result->recipientEmail,
                        channel: $result->channel,
                        messageId: $result->messageId,
                        metadata: array_merge($result->metadata, [
                            'attempts_used' => $attempt,
                        ]),
                    );
                }
            } catch (\Throwable $e) {
                $lastException = $e;

                if ($attempt >= $this->maxAttempts) {
                    throw $e;
                }
            }

            if ($this->delayMilliseconds > 0) {
                usleep($this->delayMilliseconds * 1000);
            }
        }

        if ($lastException !== null) {
            throw $lastException;
        }

        return NotificationResult::failure(
            recipientEmail: $recipient->email,
            metadata: ['attempts_used' => $attempt],
        );
    }
}

<?php

declare(strict_types=1);

namespace App\Infrastructure\Notifications;

use App\Domain\Identity\Models\User;

final class MetricsNotificationSenderDecorator implements NotificationSenderInterface
{
    private int $sentCount = 0;

    private int $failureCount = 0;

    /** @var array<int, float> */
    private array $durations = [];

    public function __construct(
        private readonly NotificationSenderInterface $inner,
    ) {}

    /**
     * @param  array<string, mixed>  $context
     */
    public function send(User $recipient, string $title, string $content, array $context = []): NotificationResult
    {
        $startTime = microtime(true);

        try {
            $result = $this->inner->send($recipient, $title, $content, $context);

            if ($result->successful) {
                $this->sentCount++;
            } else {
                $this->failureCount++;
            }

            return new NotificationResult(
                successful: $result->successful,
                recipientEmail: $result->recipientEmail,
                channel: $result->channel,
                messageId: $result->messageId,
                metadata: array_merge($result->metadata, [
                    'metrics_tracked' => true,
                    'execution_time_ms' => round((microtime(true) - $startTime) * 1000, 2),
                ]),
            );
        } catch (\Throwable $e) {
            $this->failureCount++;
            throw $e;
        } finally {
            $this->durations[] = microtime(true) - $startTime;
        }
    }

    public function getSentCount(): int
    {
        return $this->sentCount;
    }

    public function getFailureCount(): int
    {
        return $this->failureCount;
    }

    /**
     * @return array<int, float>
     */
    public function getDurations(): array
    {
        return $this->durations;
    }
}

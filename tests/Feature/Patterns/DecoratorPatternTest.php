<?php

declare(strict_types=1);

use App\Domain\Identity\Models\User;
use App\Infrastructure\Notifications\EmailNotificationSender;
use App\Infrastructure\Notifications\LoggingNotificationSenderDecorator;
use App\Infrastructure\Notifications\MetricsNotificationSenderDecorator;
use App\Infrastructure\Notifications\NotificationResult;
use App\Infrastructure\Notifications\NotificationSenderInterface;
use App\Infrastructure\Notifications\RetryNotificationSenderDecorator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Log;
use Psr\Log\LoggerInterface;

uses(RefreshDatabase::class);

test('base email notification sender successfully sends notification', function (): void {
    $user = User::factory()->create(['email' => 'customer@example.com']);

    $sender = new EmailNotificationSender;
    $result = $sender->send($user, 'Ticket Received', 'We are looking into your issue.');

    expect($result->successful)->toBeTrue()
        ->and($result->recipientEmail)->toBe('customer@example.com')
        ->and($result->channel)->toBe('email')
        ->and($result->messageId)->not->toBeNull()
        ->and($result->metadata['title'])->toBe('Ticket Received');
});

test('logging decorator records log entries before and after sending', function (): void {
    $user = User::factory()->create(['email' => 'user@example.com']);
    $logger = Mockery::mock(LoggerInterface::class);

    $logger->shouldReceive('info')
        ->once()
        ->with('Sending notification', Mockery::on(fn (array $data) => $data['recipient'] === 'user@example.com' && $data['title'] === 'System Alert'));

    $logger->shouldReceive('info')
        ->once()
        ->with('Notification sent', Mockery::on(fn (array $data) => $data['recipient'] === 'user@example.com' && $data['successful'] === true));

    $baseSender = new EmailNotificationSender;
    $decorator = new LoggingNotificationSenderDecorator($baseSender, $logger);

    $result = $decorator->send($user, 'System Alert', 'Database maintenance in 10 minutes.');
    expect($result->successful)->toBeTrue();
});

test('metrics decorator tracks execution times and counts', function (): void {
    $user = User::factory()->create();

    $baseSender = new EmailNotificationSender;
    $metricsDecorator = new MetricsNotificationSenderDecorator($baseSender);

    expect($metricsDecorator->getSentCount())->toBe(0);

    $result1 = $metricsDecorator->send($user, 'Test 1', 'Content 1');
    $result2 = $metricsDecorator->send($user, 'Test 2', 'Content 2');

    expect($metricsDecorator->getSentCount())->toBe(2)
        ->and($metricsDecorator->getFailureCount())->toBe(0)
        ->and(count($metricsDecorator->getDurations()))->toBe(2)
        ->and($result1->metadata['metrics_tracked'])->toBeTrue()
        ->and($result2->metadata['execution_time_ms'])->toBeGreaterThanOrEqual(0);
});

test('retry decorator retries failed attempts and succeeds if subsequent attempt succeeds', function (): void {
    $user = User::factory()->create();

    $failingSender = new class implements NotificationSenderInterface
    {
        public int $calls = 0;

        public function send(User $recipient, string $title, string $content, array $context = []): NotificationResult
        {
            $this->calls++;
            if ($this->calls < 3) {
                return NotificationResult::failure($recipient->email, metadata: ['fail' => true]);
            }

            return NotificationResult::success($recipient->email, messageId: 'retry-success');
        }
    };

    $retryDecorator = new RetryNotificationSenderDecorator(
        inner: $failingSender,
        maxAttempts: 3,
        delayMilliseconds: 0
    );

    $result = $retryDecorator->send($user, 'Retry Subject', 'Content');

    expect($failingSender->calls)->toBe(3)
        ->and($result->successful)->toBeTrue()
        ->and($result->metadata['attempts_used'])->toBe(3);
});

test('stacked decorator pipeline composes logging, metrics, and retry around base sender', function (): void {
    $user = User::factory()->create(['email' => 'pipeline@example.com']);
    Log::spy();

    $baseSender = new EmailNotificationSender;
    $withRetry = new RetryNotificationSenderDecorator($baseSender, maxAttempts: 2);
    $withMetrics = new MetricsNotificationSenderDecorator($withRetry);
    $withLogging = new LoggingNotificationSenderDecorator($withMetrics);

    $result = $withLogging->send($user, 'Composed Title', 'Composed Content', ['priority' => 'high']);

    expect($result->successful)->toBeTrue()
        ->and($withMetrics->getSentCount())->toBe(1)
        ->and($result->metadata['attempts_used'])->toBe(1)
        ->and($result->metadata['metrics_tracked'])->toBeTrue();
});

<?php

declare(strict_types=1);

use App\Domain\Identity\Models\User;
use App\Infrastructure\Notifications\Adapters\SendGridEmailAdapter;
use App\Infrastructure\Notifications\Adapters\SlackNotificationAdapter;
use App\Infrastructure\Notifications\Adapters\TwilioSmsAdapter;
use App\Infrastructure\Notifications\LoggingNotificationSenderDecorator;
use App\Infrastructure\Notifications\MetricsNotificationSenderDecorator;
use App\Infrastructure\Notifications\NotificationManager;
use App\Infrastructure\Notifications\NotificationSenderInterface;
use App\Infrastructure\Notifications\RetryNotificationSenderDecorator;
use App\Infrastructure\Notifications\ThirdParty\SendGridClient;
use App\Infrastructure\Notifications\ThirdParty\SlackWebhookClient;
use App\Infrastructure\Notifications\ThirdParty\TwilioSmsClient;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Log;

uses(RefreshDatabase::class);

test('SendGridEmailAdapter adapts domain notification into SendGrid API payload and returns normalized result', function (): void {
    $user = User::factory()->create([
        'first_name' => 'Jane',
        'last_name' => 'Doe',
        'email' => 'jane.doe@example.com',
    ]);

    $mockClient = Mockery::mock(SendGridClient::class);
    $mockClient->shouldReceive('sendMail')
        ->once()
        ->with(
            'jane.doe@example.com',
            'Welcome to Help Desk',
            'Your account has been created.',
            true,
            ['X-Entity-Ref-ID' => 'ticket-123']
        )
        ->andReturn([
            'status_code' => 202,
            'x_message_id' => 'sg_msg_abc123',
            'timestamp' => 1700000000,
        ]);

    $adapter = new SendGridEmailAdapter($mockClient);

    $result = $adapter->send(
        recipient: $user,
        title: 'Welcome to Help Desk',
        content: 'Your account has been created.',
        context: [
            'is_html' => true,
            'headers' => ['X-Entity-Ref-ID' => 'ticket-123'],
        ]
    );

    expect($result->successful)->toBeTrue()
        ->and($result->recipientEmail)->toBe('jane.doe@example.com')
        ->and($result->channel)->toBe('email')
        ->and($result->messageId)->toBe('sg_msg_abc123')
        ->and($result->metadata['provider'])->toBe('sendgrid')
        ->and($result->metadata['status_code'])->toBe(202);
});

test('SendGridEmailAdapter handles non-200 responses and client exceptions gracefully', function (): void {
    $user = User::factory()->create(['email' => 'error@example.com']);

    $mockClient = Mockery::mock(SendGridClient::class);
    $mockClient->shouldReceive('sendMail')
        ->once()
        ->andReturn([
            'status_code' => 401,
            'x_message_id' => '',
            'timestamp' => time(),
        ]);

    $adapter = new SendGridEmailAdapter($mockClient);
    $result = $adapter->send($user, 'Test', 'Body');

    expect($result->successful)->toBeFalse()
        ->and($result->channel)->toBe('email')
        ->and($result->metadata['status_code'])->toBe(401);

    // Test client exception handling
    $throwingClient = Mockery::mock(SendGridClient::class);
    $throwingClient->shouldReceive('sendMail')
        ->once()
        ->andThrow(new RuntimeException('SendGrid rate limit exceeded'));

    $throwingAdapter = new SendGridEmailAdapter($throwingClient);
    $failResult = $throwingAdapter->send($user, 'Test', 'Body');

    expect($failResult->successful)->toBeFalse()
        ->and($failResult->metadata['error'])->toBe('SendGrid rate limit exceeded');
});

test('SlackNotificationAdapter adapts domain notification into Slack Block Kit payload', function (): void {
    $user = User::factory()->create([
        'first_name' => 'Alice',
        'last_name' => 'Smith',
        'email' => 'alice@example.com',
    ]);

    $mockClient = Mockery::mock(SlackWebhookClient::class);
    $mockClient->shouldReceive('postJson')
        ->once()
        ->with(Mockery::on(function (array $payload) use ($user) {
            return str_contains($payload['text'], 'Urgent SLA Breach')
                && $payload['blocks'][0]['type'] === 'header'
                && $payload['blocks'][0]['text']['text'] === 'Urgent SLA Breach'
                && $payload['blocks'][1]['type'] === 'section'
                && $payload['blocks'][1]['text']['text'] === 'Ticket #404 requires triage'
                && str_contains($payload['blocks'][2]['elements'][0]['text'], $user->email)
                && $payload['channel'] === '#ops-alerts';
        }))
        ->andReturnTrue();

    $adapter = new SlackNotificationAdapter($mockClient, defaultChannel: '#general');

    $result = $adapter->send(
        recipient: $user,
        title: 'Urgent SLA Breach',
        content: 'Ticket #404 requires triage',
        context: ['slack_channel' => '#ops-alerts']
    );

    expect($result->successful)->toBeTrue()
        ->and($result->recipientEmail)->toBe('alice@example.com')
        ->and($result->channel)->toBe('slack')
        ->and($result->messageId)->not->toBeNull()
        ->and($result->metadata['provider'])->toBe('slack')
        ->and($result->metadata['slack_channel'])->toBe('#ops-alerts');
});

test('SlackNotificationAdapter handles webhook failures and network exceptions', function (): void {
    $user = User::factory()->create();

    $failingClient = Mockery::mock(SlackWebhookClient::class);
    $failingClient->shouldReceive('postJson')->once()->andReturnFalse();

    $adapter = new SlackNotificationAdapter($failingClient);
    $result = $adapter->send($user, 'Alert', 'Body');

    expect($result->successful)->toBeFalse()
        ->and($result->channel)->toBe('slack')
        ->and($result->metadata['error'])->toContain('unsuccessful');

    $throwingClient = Mockery::mock(SlackWebhookClient::class);
    $throwingClient->shouldReceive('postJson')->once()->andThrow(new RuntimeException('Connection timed out'));

    $throwingAdapter = new SlackNotificationAdapter($throwingClient);
    $failResult = $throwingAdapter->send($user, 'Alert', 'Body');

    expect($failResult->successful)->toBeFalse()
        ->and($failResult->metadata['error'])->toBe('Connection timed out');
});

test('TwilioSmsAdapter adapts notification into SMS payload and dispatches via Twilio client', function (): void {
    $user = User::factory()->create([
        'first_name' => 'Bob',
        'last_name' => 'Jones',
        'email' => 'bob@example.com',
    ]);

    $mockClient = Mockery::mock(TwilioSmsClient::class);
    $mockClient->shouldReceive('createMessage')
        ->once()
        ->with(
            '+15551234567',
            ['body' => 'Password Reset: Your code is 123456']
        )
        ->andReturn((object) [
            'sid' => 'SMabcdef123456',
            'status' => 'queued',
            'to' => '+15551234567',
        ]);

    $adapter = new TwilioSmsAdapter($mockClient);

    $result = $adapter->send(
        recipient: $user,
        title: 'Password Reset',
        content: 'Your code is 123456',
        context: ['phone_number' => '+15551234567']
    );

    expect($result->successful)->toBeTrue()
        ->and($result->channel)->toBe('sms')
        ->and($result->messageId)->toBe('SMabcdef123456')
        ->and($result->metadata['provider'])->toBe('twilio')
        ->and($result->metadata['destination_phone'])->toBe('+15551234567')
        ->and($result->metadata['status'])->toBe('queued');
});

test('TwilioSmsAdapter returns failure when recipient phone number is missing or SDK throws exception', function (): void {
    $user = User::factory()->create();

    $mockClient = Mockery::mock(TwilioSmsClient::class);
    $mockClient->shouldReceive('createMessage')->never();

    $adapter = new TwilioSmsAdapter($mockClient);

    // Missing phone number
    $result = $adapter->send($user, 'Title', 'Content', []);

    expect($result->successful)->toBeFalse()
        ->and($result->channel)->toBe('sms')
        ->and($result->metadata['error'])->toContain('Recipient phone number is missing');

    // SDK Exception
    $throwingClient = Mockery::mock(TwilioSmsClient::class);
    $throwingClient->shouldReceive('createMessage')
        ->once()
        ->andThrow(new RuntimeException('Twilio authentication failed'));

    $throwingAdapter = new TwilioSmsAdapter($throwingClient);
    $failResult = $throwingAdapter->send($user, 'Title', 'Content', ['phone_number' => '+19999999999']);

    expect($failResult->successful)->toBeFalse()
        ->and($failResult->metadata['error'])->toBe('Twilio authentication failed');
});

test('NotificationManager resolves adapters for email, sendgrid, slack, and twilio drivers', function (): void {
    $manager = app(NotificationManager::class);

    $emailSender = $manager->driver('email');
    $sendgridSender = $manager->driver('sendgrid');
    $slackSender = $manager->driver('slack');
    $twilioSender = $manager->driver('twilio');
    $smsSender = $manager->driver('sms');

    expect($emailSender)->toBeInstanceOf(SendGridEmailAdapter::class)
        ->and($sendgridSender)->toBeInstanceOf(SendGridEmailAdapter::class)
        ->and($slackSender)->toBeInstanceOf(SlackNotificationAdapter::class)
        ->and($twilioSender)->toBeInstanceOf(TwilioSmsAdapter::class)
        ->and($smsSender)->toBeInstanceOf(TwilioSmsAdapter::class);
});

test('Adapters seamlessly compose with Logging, Metrics, and Retry decorators', function (): void {
    $user = User::factory()->create();
    Log::spy();

    $mockClient = Mockery::mock(SendGridClient::class);
    $mockClient->shouldReceive('sendMail')
        ->once()
        ->andReturn([
            'status_code' => 202,
            'x_message_id' => 'sg_decorated_123',
            'timestamp' => time(),
        ]);

    $baseAdapter = new SendGridEmailAdapter($mockClient);
    $withRetry = new RetryNotificationSenderDecorator($baseAdapter, maxAttempts: 2);
    $withMetrics = new MetricsNotificationSenderDecorator($withRetry);
    $withLogging = new LoggingNotificationSenderDecorator($withMetrics);

    $result = $withLogging->send($user, 'Composed Notification', 'Testing composition with Adapter');

    expect($result->successful)->toBeTrue()
        ->and($result->channel)->toBe('email')
        ->and($result->messageId)->toBe('sg_decorated_123')
        ->and($result->metadata['attempts_used'])->toBe(1)
        ->and($result->metadata['metrics_tracked'])->toBeTrue()
        ->and($withMetrics->getSentCount())->toBe(1);
});

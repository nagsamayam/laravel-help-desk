<?php

declare(strict_types=1);

namespace App\Infrastructure\Notifications;

use App\Infrastructure\Notifications\Adapters\SendGridEmailAdapter;
use App\Infrastructure\Notifications\Adapters\SlackNotificationAdapter;
use App\Infrastructure\Notifications\Adapters\TwilioSmsAdapter;
use App\Infrastructure\Notifications\ThirdParty\SendGridClient;
use App\Infrastructure\Notifications\ThirdParty\SlackWebhookClient;
use App\Infrastructure\Notifications\ThirdParty\TwilioSmsClient;
use Illuminate\Support\Manager;

class NotificationManager extends Manager
{
    /**
     * Get the default driver name.
     */
    public function getDefaultDriver(): string
    {
        return (string) $this->config->get('services.notifications.default', 'email');
    }

    /**
     * Create an instance of the SendGrid Email adapter driver.
     */
    public function createSendgridDriver(): NotificationSenderInterface
    {
        $apiKey = (string) $this->config->get('services.sendgrid.api_key', 'test-sendgrid-key');
        $client = new SendGridClient($apiKey);

        return new SendGridEmailAdapter($client);
    }

    /**
     * Create an instance of the default Email adapter driver.
     */
    public function createEmailDriver(): NotificationSenderInterface
    {
        return $this->createSendgridDriver();
    }

    /**
     * Create an instance of the Slack adapter driver.
     */
    public function createSlackDriver(): NotificationSenderInterface
    {
        $webhookUrl = (string) $this->config->get(
            'services.slack.webhook_url',
            'https://hooks.slack.com/services/test/webhook'
        );
        $defaultChannel = $this->config->get('services.slack.notifications.channel');

        $client = new SlackWebhookClient($webhookUrl);

        return new SlackNotificationAdapter($client, $defaultChannel);
    }

    /**
     * Create an instance of the Twilio SMS adapter driver.
     */
    public function createTwilioDriver(): NotificationSenderInterface
    {
        $accountSid = (string) $this->config->get('services.twilio.account_sid', 'AC_test_account_sid');
        $authToken = (string) $this->config->get('services.twilio.auth_token', 'test_auth_token');
        $fromNumber = (string) $this->config->get('services.twilio.from_number', '+15005550006');

        $client = new TwilioSmsClient($accountSid, $authToken, $fromNumber);

        return new TwilioSmsAdapter($client);
    }

    /**
     * Create an instance of the SMS adapter driver.
     */
    public function createSmsDriver(): NotificationSenderInterface
    {
        return $this->createTwilioDriver();
    }
}

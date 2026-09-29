<?php

declare(strict_types=1);

namespace App\Infrastructure\Notifications\Providers;

use App\Infrastructure\Notifications\EmailNotificationSender;
use App\Infrastructure\Notifications\LoggingNotificationSenderDecorator;
use App\Infrastructure\Notifications\MetricsNotificationSenderDecorator;
use App\Infrastructure\Notifications\NotificationSenderInterface;
use App\Infrastructure\Notifications\RetryNotificationSenderDecorator;
use Illuminate\Support\ServiceProvider;

final class NotificationServiceProvider extends ServiceProvider
{
    /**
     * Register notification infrastructure services.
     */
    public function register(): void
    {
        $this->app->singleton(NotificationSenderInterface::class, function () {
            $sender = new EmailNotificationSender;

            return new LoggingNotificationSenderDecorator(
                new MetricsNotificationSenderDecorator(
                    new RetryNotificationSenderDecorator($sender)
                )
            );
        });
    }
}

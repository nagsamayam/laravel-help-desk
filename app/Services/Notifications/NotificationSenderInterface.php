<?php

declare(strict_types=1);

namespace App\Services\Notifications;

use App\Models\User;

interface NotificationSenderInterface
{
    /**
     * @param  array<string, mixed>  $context
     */
    public function send(User $recipient, string $title, string $content, array $context = []): NotificationResult;
}

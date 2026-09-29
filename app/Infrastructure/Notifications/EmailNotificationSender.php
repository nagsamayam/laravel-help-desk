<?php

declare(strict_types=1);

namespace App\Infrastructure\Notifications;

use App\Domain\Identity\Models\User;
use Illuminate\Support\Str;

final class EmailNotificationSender implements NotificationSenderInterface
{
    /**
     * @param  array<string, mixed>  $context
     */
    public function send(User $recipient, string $title, string $content, array $context = []): NotificationResult
    {
        $messageId = (string) Str::uuid();

        return NotificationResult::success(
            recipientEmail: $recipient->email,
            channel: 'email',
            messageId: $messageId,
            metadata: array_merge($context, [
                'title' => $title,
                'content_length' => strlen($content),
            ]),
        );
    }
}

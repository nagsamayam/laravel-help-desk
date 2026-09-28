<?php

declare(strict_types=1);

namespace App\Services\Notifications;

final readonly class NotificationResult
{
    /**
     * @param  array<string, mixed>  $metadata
     */
    public function __construct(
        public bool $successful,
        public string $recipientEmail,
        public string $channel,
        public ?string $messageId = null,
        public array $metadata = [],
    ) {}

    /**
     * @param  array<string, mixed>  $metadata
     */
    public static function success(
        string $recipientEmail,
        string $channel = 'email',
        ?string $messageId = null,
        array $metadata = [],
    ): self {
        return new self(
            successful: true,
            recipientEmail: $recipientEmail,
            channel: $channel,
            messageId: $messageId,
            metadata: $metadata,
        );
    }

    /**
     * @param  array<string, mixed>  $metadata
     */
    public static function failure(
        string $recipientEmail,
        string $channel = 'email',
        array $metadata = [],
    ): self {
        return new self(
            successful: false,
            recipientEmail: $recipientEmail,
            channel: $channel,
            messageId: null,
            metadata: $metadata,
        );
    }
}

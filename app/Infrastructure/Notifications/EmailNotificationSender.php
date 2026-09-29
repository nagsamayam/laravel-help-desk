<?php

declare(strict_types=1);

namespace App\Infrastructure\Notifications;

use App\Domain\Identity\Models\User;
use App\Domain\Ticket\Mail\TicketNotificationMail;
use App\Domain\Ticket\Models\Ticket;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;

final class EmailNotificationSender implements NotificationSenderInterface
{
    /**
     * @param  array<string, mixed>  $context
     */
    public function send(User $recipient, string $title, string $content, array $context = []): NotificationResult
    {
        $ticket = null;
        if (! empty($context['ticket']) && $context['ticket'] instanceof Ticket) {
            $ticket = $context['ticket'];
        } elseif (! empty($context['ticket_id'])) {
            $ticket = Ticket::query()->find($context['ticket_id']);
        }

        $mailable = new TicketNotificationMail(
            recipient: $recipient,
            subjectTitle: $title,
            contentMessage: $content,
            context: $context,
            ticket: $ticket,
        );

        $sentMessage = Mail::to($recipient->email)->send($mailable);

        $messageId = $sentMessage?->getMessageId() ?? (string) Str::uuid();

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

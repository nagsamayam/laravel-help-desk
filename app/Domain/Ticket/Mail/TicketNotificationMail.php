<?php

declare(strict_types=1);

namespace App\Domain\Ticket\Mail;

use App\Domain\Identity\Models\User;
use App\Domain\Ticket\Models\Ticket;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Address;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

final class TicketNotificationMail extends Mailable
{
    use Queueable, SerializesModels;

    /**
     * @param  array<string, mixed>  $context
     */
    public function __construct(
        public readonly User $recipient,
        public readonly string $subjectTitle,
        public readonly string $contentMessage,
        public readonly array $context = [],
        public readonly ?Ticket $ticket = null,
    ) {}

    /**
     * Get the message envelope.
     */
    public function envelope(): Envelope
    {
        $fromAddress = (string) config('mail.from.address', 'hello@example.com');
        $fromName = (string) config('mail.from.name', config('app.name', 'Help Desk'));

        return new Envelope(
            from: new Address($fromAddress, $fromName),
            to: [$this->recipient->email],
            subject: $this->subjectTitle,
        );
    }

    /**
     * Get the message content definition.
     */
    public function content(): Content
    {
        return new Content(
            view: 'emails.ticket-notification',
            text: 'emails.ticket-notification-text',
            with: [
                'recipient' => $this->recipient,
                'title' => $this->subjectTitle,
                'contentMessage' => $this->contentMessage,
                'context' => $this->context,
                'ticket' => $this->ticket,
                'appUrl' => (string) config('app.url', 'http://localhost:8000'),
            ],
        );
    }
}

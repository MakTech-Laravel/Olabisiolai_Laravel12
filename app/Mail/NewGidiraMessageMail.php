<?php

declare(strict_types=1);

namespace App\Mail;

use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

final class NewGidiraMessageMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public readonly User $recipient,
        public readonly string $senderName,
        public readonly string $actionUrl,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'New message on Gidira',
        );
    }

    public function content(): Content
    {
        return new Content(
            markdown: 'mail.gidira-new-message',
        );
    }

    /**
     * @return list<mixed>
     */
    public function attachments(): array
    {
        return [];
    }
}

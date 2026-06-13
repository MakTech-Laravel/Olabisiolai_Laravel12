<?php

namespace App\Mail;

use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class WelcomeVendorMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public readonly User $user) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Welcome to '.config('app.name').' — complete your vendor profile',
        );
    }

    public function content(): Content
    {
        return new Content(
            markdown: 'mail.auth.welcome-vendor',
        );
    }

    public function attachments(): array
    {
        return [];
    }
}

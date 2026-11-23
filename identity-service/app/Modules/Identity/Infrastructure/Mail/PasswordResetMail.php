<?php

declare(strict_types=1);

namespace App\Modules\Identity\Infrastructure\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

final class PasswordResetMail extends Mailable
{
    use Queueable;

    public function __construct(public readonly string $url)
    {
    }

    public function envelope(): Envelope
    {
        return new Envelope(subject: 'Reset your password');
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.identity.reset-password',
            with: ['url' => $this->url],
        );
    }
}

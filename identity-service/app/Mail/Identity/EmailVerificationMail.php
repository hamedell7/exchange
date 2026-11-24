<?php

declare(strict_types=1);

namespace App\Mail\Identity;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

final class EmailVerificationMail extends Mailable
{
    use Queueable;

    public function __construct(public readonly string $url)
    {
    }

    public function envelope(): Envelope
    {
        return new Envelope(subject: 'Verify your email address');
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.identity.verify-email',
            with: ['url' => $this->url],
        );
    }
}

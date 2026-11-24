<?php

declare(strict_types=1);

namespace App\Services\Identity;

use App\Mail\Identity\EmailVerificationMail;
use App\Mail\Identity\PasswordResetMail;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Throwable;

final class IdentityMailService
{
    public function sendEmailVerification(string $email, string $plainToken): void
    {
        $url = $this->frontendUrl('/verify-email', $plainToken);

        try {
            Mail::to($email)->send(new EmailVerificationMail($url));
        } catch (Throwable $exception) {
            // Never log the email, URL, or token. The resend endpoint can recover.
            Log::error('Identity email verification delivery failed.', [
                'exception' => $exception::class,
            ]);
        }
    }

    public function sendPasswordReset(string $email, string $plainToken): void
    {
        $url = $this->frontendUrl('/reset-password', $plainToken);

        try {
            Mail::to($email)->send(new PasswordResetMail($url));
        } catch (Throwable $exception) {
            Log::error('Identity password reset delivery failed.', [
                'exception' => $exception::class,
            ]);
        }
    }

    private function frontendUrl(string $path, string $plainToken): string
    {
        $baseUrl = rtrim((string) config('identity.frontend_url'), '/');

        // A URL fragment is not sent to the frontend server or in Referer headers.
        return $baseUrl.$path.'#token='.urlencode($plainToken);
    }
}

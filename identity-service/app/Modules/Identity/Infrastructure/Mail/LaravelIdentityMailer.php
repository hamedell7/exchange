<?php

declare(strict_types=1);

namespace App\Modules\Identity\Infrastructure\Mail;

use App\Modules\Identity\Application\Contracts\IdentityMailer;
use App\Modules\Identity\Domain\ValueObjects\EmailAddress;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Throwable;

final class LaravelIdentityMailer implements IdentityMailer
{
    public function sendEmailVerification(EmailAddress $email, string $plainToken): void
    {
        $url = $this->frontendUrl('/verify-email', $plainToken);

        try {
            Mail::to($email->value())->send(new EmailVerificationMail($url));
        } catch (Throwable $exception) {
            // Never log the email, URL, or token. A resend request can recover
            // after a mail outage without disclosing account existence.
            Log::error('Identity email verification delivery failed.', [
                'exception' => $exception::class,
            ]);
        }
    }

    public function sendPasswordReset(EmailAddress $email, string $plainToken): void
    {
        $url = $this->frontendUrl('/reset-password', $plainToken);

        try {
            Mail::to($email->value())->send(new PasswordResetMail($url));
        } catch (Throwable $exception) {
            Log::error('Identity password reset delivery failed.', [
                'exception' => $exception::class,
            ]);
        }
    }

    private function frontendUrl(string $path, string $plainToken): string
    {
        $baseUrl = rtrim((string) config('identity.frontend_url'), '/');

        // A URL fragment is not sent to the frontend server or in Referer
        // headers. The frontend must submit it to the API in a POST body.
        return $baseUrl.$path.'#token='.urlencode($plainToken);
    }
}

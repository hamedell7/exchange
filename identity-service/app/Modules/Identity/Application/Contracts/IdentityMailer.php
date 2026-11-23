<?php

declare(strict_types=1);

namespace App\Modules\Identity\Application\Contracts;

use App\Modules\Identity\Domain\ValueObjects\EmailAddress;

interface IdentityMailer
{
    public function sendEmailVerification(EmailAddress $email, string $plainToken): void;

    public function sendPasswordReset(EmailAddress $email, string $plainToken): void;
}

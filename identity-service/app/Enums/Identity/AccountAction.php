<?php

declare(strict_types=1);

namespace App\Enums\Identity;

enum AccountAction: string
{
    case VerifyEmail = 'verify_email';
    case ResetPassword = 'reset_password';
}

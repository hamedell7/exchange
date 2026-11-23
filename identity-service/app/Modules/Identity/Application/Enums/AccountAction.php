<?php

declare(strict_types=1);

namespace App\Modules\Identity\Application\Enums;

enum AccountAction: string
{
    case VerifyEmail = 'verify_email';
    case ResetPassword = 'reset_password';
}

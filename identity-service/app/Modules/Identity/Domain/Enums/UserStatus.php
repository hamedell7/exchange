<?php

declare(strict_types=1);

namespace App\Modules\Identity\Domain\Enums;

enum UserStatus: string
{
    case PendingVerification = 'pending_verification';
    case Active = 'active';
    case Suspended = 'suspended';
    case Closed = 'closed';
}

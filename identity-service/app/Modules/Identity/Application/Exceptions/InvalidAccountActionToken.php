<?php

declare(strict_types=1);

namespace App\Modules\Identity\Application\Exceptions;

use RuntimeException;

final class InvalidAccountActionToken extends RuntimeException
{
    public static function expiredOrInvalid(): self
    {
        return new self('The token is invalid, expired, or has already been used.');
    }
}

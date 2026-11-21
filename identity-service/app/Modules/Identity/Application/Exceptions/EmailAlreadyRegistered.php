<?php

declare(strict_types=1);

namespace App\Modules\Identity\Application\Exceptions;

use RuntimeException;
use Throwable;

final class EmailAlreadyRegistered extends RuntimeException
{
    public static function becauseEmailIsAlreadyRegistered(Throwable $previous): self
    {
        return new self('The email address is already registered.', 0, $previous);
    }
}

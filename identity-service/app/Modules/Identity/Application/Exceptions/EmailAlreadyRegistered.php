<?php

declare(strict_types=1);

namespace App\Modules\Identity\Application\Exceptions;

use DomainException;

final class EmailAlreadyRegistered extends DomainException
{
    public static function for(string $email): self
    {
        return new self("The email address [{$email}] is already registered.");
    }
}

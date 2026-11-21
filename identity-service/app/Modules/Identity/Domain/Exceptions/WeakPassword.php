<?php

declare(strict_types=1);

namespace App\Modules\Identity\Domain\Exceptions;

use DomainException;

final class WeakPassword extends DomainException
{
    public static function minimumLength(int $minimumLength): self
    {
        return new self(
            sprintf('A password must contain at least %d characters.', $minimumLength),
        );
    }
}

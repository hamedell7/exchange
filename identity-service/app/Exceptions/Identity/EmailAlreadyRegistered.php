<?php

declare(strict_types=1);

namespace App\Exceptions\Identity;

use RuntimeException;
use Throwable;

final class EmailAlreadyRegistered extends RuntimeException
{
    public function __construct(?Throwable $previous = null)
    {
        parent::__construct('The email address is already registered.', 0, $previous);
    }
}

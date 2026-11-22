<?php

declare(strict_types=1);

namespace App\Modules\Identity\Application\Exceptions;

use RuntimeException;
use Throwable;

/**
 * A registration input error that can be mapped to a field-level API error.
 */
final class InvalidRegistrationInput extends RuntimeException
{
    public function __construct(
        public readonly string $field,
        string $message,
        ?Throwable $previous = null,
    ) {
        parent::__construct($message, 0, $previous);
    }
}

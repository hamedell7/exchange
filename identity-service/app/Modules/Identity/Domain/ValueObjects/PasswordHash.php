<?php

declare(strict_types=1);

namespace App\Modules\Identity\Domain\ValueObjects;

use InvalidArgumentException;

/**
 * An already-generated password hash.
 *
 * Hash generation and algorithm selection belong to an application port and
 * infrastructure adapter, never to this domain value object.
 */
final readonly class PasswordHash
{
    private string $value;

    public function __construct(string $value)
    {
        if (trim($value) === '') {
            throw new InvalidArgumentException('A password hash is required.');
        }

        $this->value = $value;
    }

    public function value(): string
    {
        return $this->value;
    }
}

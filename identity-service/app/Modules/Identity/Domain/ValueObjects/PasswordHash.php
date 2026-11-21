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
    private function __construct(private string $value)
    {
    }

    public static function fromEncodedHash(string $value): self
    {
        if (trim($value) === '' || password_get_info($value)['algo'] === null) {
            throw new InvalidArgumentException('A valid encoded password hash is required.');
        }

        return new self($value);
    }

    public function value(): string
    {
        return $this->value;
    }
}

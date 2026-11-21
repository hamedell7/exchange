<?php

declare(strict_types=1);

namespace App\Modules\Identity\Domain\ValueObjects;

use InvalidArgumentException;

final readonly class UserId
{
    private string $value;

    public function __construct(string $value)
    {
        $value = strtoupper(trim($value));

        // ULID: 26 Crockford Base32 characters; the first character is 0-7.
        if (preg_match('/\A[0-7][0-9A-HJKMNP-TV-Z]{25}\z/', $value) !== 1) {
            throw new InvalidArgumentException('A valid ULID user identifier is required.');
        }

        $this->value = $value;
    }

    public function value(): string
    {
        return $this->value;
    }

    public function equals(self $other): bool
    {
        return $this->value === $other->value;
    }

    public function __toString(): string
    {
        return $this->value;
    }
}

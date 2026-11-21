<?php

declare(strict_types=1);

namespace App\Modules\Identity\Domain\ValueObjects;

use InvalidArgumentException;

final readonly class EmailAddress
{
    private string $value;

    public function __construct(string $value)
    {
        $candidate = trim($value);

        if ($candidate === '' || filter_var($candidate, FILTER_VALIDATE_EMAIL) === false) {
            throw new InvalidArgumentException('The email address is invalid.');
        }

        $separator = strrpos($candidate, '@');
        $localPart = substr($candidate, 0, $separator);
        $domain = strtolower(substr($candidate, $separator + 1));

        // The domain is case-insensitive. Preserve the local part because
        // email standards do not guarantee that it is case-insensitive.
        $this->value = $localPart.'@'.$domain;
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

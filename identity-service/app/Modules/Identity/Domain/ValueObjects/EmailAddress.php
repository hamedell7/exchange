<?php

declare(strict_types=1);

namespace App\Modules\Identity\Domain\ValueObjects;

use InvalidArgumentException;

/**
 * A deliverable email address.
 *
 * Original local-part casing is preserved for display and delivery.
 * Identity comparison is case-insensitive.
 */
final readonly class EmailAddress
{
    public const MAX_LENGTH = 254;

    private string $value;

    public function __construct(string $value)
    {
        $candidate = trim($value);

        if (
            $candidate === ''
            || strlen($candidate) > self::MAX_LENGTH
            || filter_var($candidate, FILTER_VALIDATE_EMAIL) === false
        ) {
            throw new InvalidArgumentException('The email address is invalid.');
        }

        $separator = strrpos($candidate, '@');
        $localPart = substr($candidate, 0, $separator);
        $domain = strtolower(substr($candidate, $separator + 1));

        $this->value = $localPart.'@'.$domain;
    }

    public function value(): string
    {
        return $this->value;
    }

    /**
     * Canonical key for Identity account lookup and uniqueness.
     *
     * Exchange deliberately treats email account identifiers as
     * case-insensitive, while preserving the original address for delivery.
     */
    public function normalizedForIdentity(): string
    {
        return strtolower($this->value);
    }

    public function equals(self $other): bool
    {
        return $this->normalizedForIdentity() === $other->normalizedForIdentity();
    }

    public function __toString(): string
    {
        return $this->value;
    }
}

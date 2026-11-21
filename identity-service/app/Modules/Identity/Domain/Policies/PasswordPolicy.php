<?php

declare(strict_types=1);

namespace App\Modules\Identity\Domain\Policies;

use App\Modules\Identity\Domain\Exceptions\WeakPassword;

final class PasswordPolicy
{
    private const MINIMUM_LENGTH = 12;

    public function assertCompliant(string $plainPassword): void
    {
        // Count Unicode characters rather than UTF-8 bytes.
        if (preg_match('/\A.{'.self::MINIMUM_LENGTH.',}\z/us', $plainPassword) !== 1) {
            throw WeakPassword::minimumLength(self::MINIMUM_LENGTH);
        }
    }
}

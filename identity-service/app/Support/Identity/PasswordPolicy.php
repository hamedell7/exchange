<?php

declare(strict_types=1);

namespace App\Support\Identity;

use Illuminate\Validation\ValidationException;

final class PasswordPolicy
{
    public const MIN_LENGTH = 15;
    public const MAX_LENGTH = 128;

    public static function validate(string $plainPassword): void
    {
        $length = mb_strlen($plainPassword, 'UTF-8');

        if ($length < self::MIN_LENGTH) {
            throw ValidationException::withMessages([
                'password' => [sprintf('The password must contain at least %d characters.', self::MIN_LENGTH)],
            ]);
        }

        if ($length > self::MAX_LENGTH) {
            throw ValidationException::withMessages([
                'password' => [sprintf('The password may not exceed %d characters.', self::MAX_LENGTH)],
            ]);
        }
    }
}

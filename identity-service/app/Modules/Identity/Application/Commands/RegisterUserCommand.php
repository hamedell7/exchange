<?php

declare(strict_types=1);

namespace App\Modules\Identity\Application\Commands;

final readonly class RegisterUserCommand
{
    public const MIN_PASSWORD_LENGTH = 12;

    public function __construct(
        public string $name,
        public string $email,
        public string $plainPassword,
    ) {
    }
}

<?php

declare(strict_types=1);

namespace App\Modules\Identity\Application\Commands;

final readonly class RegisterUserCommand
{
    public const MIN_NAME_LENGTH = 2;
    public const MAX_NAME_LENGTH = 255;
    public const MIN_PASSWORD_LENGTH = 15;
    public const MAX_PASSWORD_LENGTH = 128;

    public function __construct(
        public string $name,
        public string $email,
        public string $plainPassword,
    ) {
    }
}

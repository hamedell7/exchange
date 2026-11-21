<?php

declare(strict_types=1);

namespace App\Modules\Identity\Application\Commands;

/**
 * Input data required to register a user.
 *
 * This command is independent of HTTP and framework request objects.
 */
final readonly class RegisterUserCommand
{
    public function __construct(
        public string $name,
        public string $email,
        public string $plainPassword,
    ) {
    }
}

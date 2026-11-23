<?php

declare(strict_types=1);

namespace App\Modules\Identity\Application\Commands;

final readonly class ResetPasswordCommand
{
    public function __construct(
        public string $token,
        public string $plainPassword,
    ) {
    }
}

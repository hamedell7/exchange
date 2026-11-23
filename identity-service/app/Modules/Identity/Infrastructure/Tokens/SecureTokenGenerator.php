<?php

declare(strict_types=1);

namespace App\Modules\Identity\Infrastructure\Tokens;

use App\Modules\Identity\Application\Contracts\TokenGenerator;

final class SecureTokenGenerator implements TokenGenerator
{
    public function generate(): string
    {
        return bin2hex(random_bytes(32));
    }
}

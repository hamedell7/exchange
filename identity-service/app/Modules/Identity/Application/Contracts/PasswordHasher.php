<?php

declare(strict_types=1);

namespace App\Modules\Identity\Application\Contracts;

use App\Modules\Identity\Domain\ValueObjects\PasswordHash;

interface PasswordHasher
{
    public function hash(string $plainText): PasswordHash;
}

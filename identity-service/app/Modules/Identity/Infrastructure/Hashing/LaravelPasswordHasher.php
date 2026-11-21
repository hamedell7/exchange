<?php

declare(strict_types=1);

namespace App\Modules\Identity\Infrastructure\Hashing;

use App\Modules\Identity\Application\Contracts\PasswordHasher;
use App\Modules\Identity\Domain\ValueObjects\PasswordHash;
use Illuminate\Support\Facades\Hash;

final class LaravelPasswordHasher implements PasswordHasher
{
    public function hash(string $plainText): PasswordHash
    {
        return PasswordHash::fromEncodedHash(Hash::make($plainText));
    }
}

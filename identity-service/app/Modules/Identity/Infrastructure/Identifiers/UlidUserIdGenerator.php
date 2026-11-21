<?php

declare(strict_types=1);

namespace App\Modules\Identity\Infrastructure\Identifiers;

use App\Modules\Identity\Application\Contracts\UserIdGenerator;
use App\Modules\Identity\Domain\ValueObjects\UserId;
use Illuminate\Support\Str;

final class UlidUserIdGenerator implements UserIdGenerator
{
    public function generate(): UserId
    {
        return new UserId((string) Str::ulid());
    }
}

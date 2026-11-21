<?php

declare(strict_types=1);

namespace App\Modules\Identity\Application\Contracts;

use App\Modules\Identity\Domain\Entities\User;

interface UserRepository
{
    public function save(User $user): void;

    public function existsByEmail(string $email): bool;
}

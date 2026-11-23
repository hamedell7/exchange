<?php

declare(strict_types=1);

namespace App\Modules\Identity\Application\Contracts;

use App\Modules\Identity\Application\Exceptions\EmailAlreadyRegistered;
use App\Modules\Identity\Domain\Entities\User;

interface UserRepository
{
    /**
     * Insert a new aggregate without overwriting an existing account.
     *
     * @throws EmailAlreadyRegistered When the unique email constraint is violated.
     */
    public function add(User $user): void;

    /**
     * Persist changes to an existing User aggregate.
     *
     * @throws EmailAlreadyRegistered When the unique email constraint is violated.
     */
    public function save(User $user): void;
}

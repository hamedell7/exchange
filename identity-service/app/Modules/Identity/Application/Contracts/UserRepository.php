<?php

declare(strict_types=1);

namespace App\Modules\Identity\Application\Contracts;

use App\Modules\Identity\Application\Exceptions\EmailAlreadyRegistered;
use App\Modules\Identity\Domain\Entities\User;
use App\Modules\Identity\Domain\ValueObjects\EmailAddress;
use App\Modules\Identity\Domain\ValueObjects\UserId;

/**
 * Persistence boundary for the User aggregate.
 *
 * Commands use this repository to reconstitute an aggregate before applying
 * domain behavior, and to persist the resulting state.
 */
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

    public function findById(UserId $id): ?User;

    public function findByEmail(EmailAddress $email): ?User;
}

<?php

declare(strict_types=1);

namespace App\Modules\Identity\Application\Contracts;

use App\Modules\Identity\Domain\Entities\User;
use App\Modules\Identity\Domain\ValueObjects\EmailAddress;
use App\Modules\Identity\Domain\ValueObjects\UserId;

/**
 * Read port for locating Identity aggregates.
 *
 * This port is kept separate from UserRepository so read persistence
 * responsibilities do not leak into the write repository contract.
 */
interface UserReader
{
    public function findById(UserId $id): ?User;

    public function findByEmail(EmailAddress $email): ?User;
}

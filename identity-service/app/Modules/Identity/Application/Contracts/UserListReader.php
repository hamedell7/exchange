<?php

declare(strict_types=1);

namespace App\Modules\Identity\Application\Contracts;

use App\Modules\Identity\Application\Queries\ListUsersQuery;
use App\Modules\Identity\Application\ReadModels\UserListPage;

/**
 * Read port for the user-list projection.
 *
 * This is deliberately separate from UserRepository: list queries do not
 * need to reconstitute write-side Domain aggregates.
 */
interface UserListReader
{
    public function paginate(ListUsersQuery $query): UserListPage;
}

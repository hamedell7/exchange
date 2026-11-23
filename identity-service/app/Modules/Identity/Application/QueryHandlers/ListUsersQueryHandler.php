<?php

declare(strict_types=1);

namespace App\Modules\Identity\Application\QueryHandlers;

use App\Modules\Identity\Application\Contracts\UserListReader;
use App\Modules\Identity\Application\Queries\ListUsersQuery;
use App\Modules\Identity\Application\ReadModels\UserListPage;

final readonly class ListUsersQueryHandler
{
    public function __construct(
        private UserListReader $users,
    ) {
    }

    public function handle(ListUsersQuery $query): UserListPage
    {
        return $this->users->paginate($query);
    }
}

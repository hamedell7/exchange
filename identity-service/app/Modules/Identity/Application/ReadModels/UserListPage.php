<?php

declare(strict_types=1);

namespace App\Modules\Identity\Application\ReadModels;

final readonly class UserListPage
{
    /**
     * @param list<UserListItem> $items
     */
    public function __construct(
        public array $items,
        public int $currentPage,
        public int $perPage,
        public int $total,
        public int $lastPage,
    ) {
    }
}

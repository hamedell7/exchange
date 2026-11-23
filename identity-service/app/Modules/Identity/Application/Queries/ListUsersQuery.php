<?php

declare(strict_types=1);

namespace App\Modules\Identity\Application\Queries;

use App\Modules\Identity\Domain\Enums\UserStatus;
use InvalidArgumentException;

final readonly class ListUsersQuery
{
    public function __construct(
        public ?string $search = null,
        public ?string $status = null,
        public int $page = 1,
        public int $perPage = 25,
    ) {
        if ($this->page < 1) {
            throw new InvalidArgumentException('Page must be greater than zero.');
        }

        if ($this->perPage < 1 || $this->perPage > 100) {
            throw new InvalidArgumentException('Per-page must be between 1 and 100.');
        }

        if ($this->status !== null && UserStatus::tryFrom($this->status) === null) {
            throw new InvalidArgumentException('The requested user status is invalid.');
        }
    }
}

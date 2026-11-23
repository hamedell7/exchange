<?php

declare(strict_types=1);

namespace App\Modules\Identity\Application\ReadModels;

use DateTimeImmutable;

final readonly class UserListItem
{
    public function __construct(
        public string $id,
        public string $name,
        public string $email,
        public string $status,
        public ?DateTimeImmutable $emailVerifiedAt,
        public DateTimeImmutable $registeredAt,
    ) {
    }
}

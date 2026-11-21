<?php

declare(strict_types=1);

namespace App\Modules\Identity\Application\Results;

use DateTimeImmutable;

/**
 * Data returned by the user registration use case.
 *
 * This keeps the Domain Entity inside the application boundary instead of
 * exposing it directly to HTTP or other presentation adapters.
 */
final readonly class RegisterUserResult
{
    public function __construct(
        public string $id,
        public string $name,
        public string $email,
        public DateTimeImmutable $registeredAt,
    ) {
    }
}

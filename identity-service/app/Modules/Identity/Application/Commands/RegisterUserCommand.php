<?php

declare(strict_types=1);

namespace App\Modules\Identity\Application\Commands;

/**
 * Input data for the RegisterUser use case.
 *
 * Business rules belong to the Domain, not to this transport-independent DTO.
 */
final readonly class RegisterUserCommand
{
    public function __construct(
        public string $name,
        public string $email,
        public string $plainPassword,
    ) {
    }
}

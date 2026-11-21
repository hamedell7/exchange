<?php

declare(strict_types=1);

namespace App\Modules\Identity\Domain\Entities;

use App\Modules\Identity\Domain\ValueObjects\EmailAddress;
use DateTimeImmutable;
use InvalidArgumentException;

/**
 * Identity User aggregate root.
 *
 * This object contains domain state only. Persistence, password hashing,
 * HTTP concerns and Laravel/Eloquent dependencies belong outside the domain.
 */
final readonly class User
{
    private function __construct(
        private string $id,
        private EmailAddress $email,
        private string $passwordHash,
        private DateTimeImmutable $registeredAt,
    ) {
    }

    public static function register(
        string $id,
        EmailAddress $email,
        string $passwordHash,
        DateTimeImmutable $registeredAt,
    ): self {
        if (trim($id) === '') {
            throw new InvalidArgumentException('A user identifier is required.');
        }

        if (trim($passwordHash) === '') {
            throw new InvalidArgumentException('A password hash is required.');
        }

        return new self($id, $email, $passwordHash, $registeredAt);
    }

    public function id(): string
    {
        return $this->id;
    }

    public function email(): EmailAddress
    {
        return $this->email;
    }

    public function passwordHash(): string
    {
        return $this->passwordHash;
    }

    public function registeredAt(): DateTimeImmutable
    {
        return $this->registeredAt;
    }
}

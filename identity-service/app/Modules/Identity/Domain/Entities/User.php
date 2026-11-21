<?php

declare(strict_types=1);

namespace App\Modules\Identity\Domain\Entities;

use App\Modules\Identity\Domain\ValueObjects\EmailAddress;
use App\Modules\Identity\Domain\ValueObjects\PasswordHash;
use App\Modules\Identity\Domain\ValueObjects\UserId;
use DateTimeImmutable;

/**
 * Identity User aggregate root.
 *
 * This first slice models registration state only. Persistence, password
 * hashing, HTTP concerns and Laravel/Eloquent dependencies stay outside
 * the domain. Further lifecycle behavior is intentionally deferred until
 * the Identity bounded-context rules are agreed.
 */
final readonly class User
{
    private function __construct(
        private UserId $id,
        private EmailAddress $email,
        private PasswordHash $passwordHash,
        private DateTimeImmutable $registeredAt,
    ) {
    }

    public static function register(
        UserId $id,
        EmailAddress $email,
        PasswordHash $passwordHash,
        DateTimeImmutable $registeredAt,
    ): self {
        return new self($id, $email, $passwordHash, $registeredAt);
    }

    public function id(): UserId
    {
        return $this->id;
    }

    public function email(): EmailAddress
    {
        return $this->email;
    }

    public function passwordHash(): PasswordHash
    {
        return $this->passwordHash;
    }

    public function registeredAt(): DateTimeImmutable
    {
        return $this->registeredAt;
    }
}

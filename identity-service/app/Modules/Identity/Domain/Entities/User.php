<?php

declare(strict_types=1);

namespace App\Modules\Identity\Domain\Entities;

use App\Modules\Identity\Domain\ValueObjects\EmailAddress;
use App\Modules\Identity\Domain\ValueObjects\PasswordHash;
use App\Modules\Identity\Domain\ValueObjects\UserId;
use DateTimeImmutable;
use InvalidArgumentException;

/**
 * Identity User aggregate root.
 *
 * Domain behavior remains independent of Laravel, Eloquent and HTTP.
 */
final readonly class User
{
    private function __construct(
        private UserId $id,
        private string $name,
        private EmailAddress $email,
        private PasswordHash $passwordHash,
        private DateTimeImmutable $registeredAt,
    ) {
    }

    public static function register(
        UserId $id,
        string $name,
        EmailAddress $email,
        PasswordHash $passwordHash,
        DateTimeImmutable $registeredAt,
    ): self {
        $name = trim($name);

        if ($name === '') {
            throw new InvalidArgumentException('A user name is required.');
        }

        return new self($id, $name, $email, $passwordHash, $registeredAt);
    }

    public function id(): UserId
    {
        return $this->id;
    }

    public function name(): string
    {
        return $this->name;
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

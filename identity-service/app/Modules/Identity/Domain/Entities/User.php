<?php

declare(strict_types=1);

namespace App\Modules\Identity\Domain\Entities;

use App\Modules\Identity\Domain\Enums\UserStatus;
use App\Modules\Identity\Domain\ValueObjects\EmailAddress;
use App\Modules\Identity\Domain\ValueObjects\PasswordHash;
use App\Modules\Identity\Domain\ValueObjects\UserId;
use DateTimeImmutable;
use DomainException;
use InvalidArgumentException;

/**
 * Identity User aggregate root.
 *
 * Domain behavior remains independent of Laravel, Eloquent and HTTP.
 */
final class User
{
    private function __construct(
        private readonly UserId $id,
        private string $name,
        private EmailAddress $email,
        private PasswordHash $passwordHash,
        private readonly DateTimeImmutable $registeredAt,
        private UserStatus $status,
        private ?DateTimeImmutable $emailVerifiedAt,
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

        return new self(
            $id,
            $name,
            $email,
            $passwordHash,
            $registeredAt,
            UserStatus::PendingVerification,
            null,
        );
    }

    /**
     * Reconstitute an aggregate from persisted state. This is an adapter
     * boundary, not a public account-creation operation.
     */
    public static function reconstitute(
        UserId $id,
        string $name,
        EmailAddress $email,
        PasswordHash $passwordHash,
        DateTimeImmutable $registeredAt,
        UserStatus $status,
        ?DateTimeImmutable $emailVerifiedAt,
    ): self {
        return new self(
            $id,
            $name,
            $email,
            $passwordHash,
            $registeredAt,
            $status,
            $emailVerifiedAt,
        );
    }

    public function verifyEmail(DateTimeImmutable $verifiedAt): void
    {
        if ($this->status !== UserStatus::PendingVerification) {
            throw new DomainException('Only a pending account can verify its email address.');
        }

        $this->status = UserStatus::Active;
        $this->emailVerifiedAt = $verifiedAt;
    }

    public function suspend(): void
    {
        if ($this->status !== UserStatus::Active) {
            throw new DomainException('Only an active account can be suspended.');
        }

        $this->status = UserStatus::Suspended;
    }

    public function reinstate(): void
    {
        if ($this->status !== UserStatus::Suspended) {
            throw new DomainException('Only a suspended account can be reinstated.');
        }

        $this->status = UserStatus::Active;
    }

    public function close(): void
    {
        if ($this->status === UserStatus::Closed) {
            throw new DomainException('The account is already closed.');
        }

        $this->status = UserStatus::Closed;
    }

    public function changePassword(PasswordHash $passwordHash): void
    {
        if ($this->status === UserStatus::Closed) {
            throw new DomainException('A closed account cannot change its password.');
        }

        $this->passwordHash = $passwordHash;
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

    public function status(): UserStatus
    {
        return $this->status;
    }

    public function emailVerifiedAt(): ?DateTimeImmutable
    {
        return $this->emailVerifiedAt;
    }
}

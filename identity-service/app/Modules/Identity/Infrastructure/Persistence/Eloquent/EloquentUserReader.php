<?php

declare(strict_types=1);

namespace App\Modules\Identity\Infrastructure\Persistence\Eloquent;

use App\Models\User as EloquentUser;
use App\Modules\Identity\Application\Contracts\UserReader;
use App\Modules\Identity\Domain\Entities\User;
use App\Modules\Identity\Domain\Enums\UserStatus;
use App\Modules\Identity\Domain\ValueObjects\EmailAddress;
use App\Modules\Identity\Domain\ValueObjects\PasswordHash;
use App\Modules\Identity\Domain\ValueObjects\UserId;
use DateTimeImmutable;

final class EloquentUserReader implements UserReader
{
    public function findById(UserId $id): ?User
    {
        $record = EloquentUser::query()->find($id->value());

        return $record === null ? null : $this->toDomain($record);
    }

    public function findByEmail(EmailAddress $email): ?User
    {
        $record = EloquentUser::query()
            ->where('email_normalized', $email->normalizedForIdentity())
            ->first();

        return $record === null ? null : $this->toDomain($record);
    }

    private function toDomain(EloquentUser $record): User
    {
        return User::reconstitute(
            new UserId((string) $record->getKey()),
            (string) $record->name,
            new EmailAddress((string) $record->email),
            PasswordHash::fromEncodedHash((string) $record->password),
            $record->created_at?->toDateTimeImmutable() ?? new DateTimeImmutable('now'),
            UserStatus::from((string) $record->status),
            $record->email_verified_at?->toDateTimeImmutable(),
        );
    }
}

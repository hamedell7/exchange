<?php

declare(strict_types=1);

namespace App\Modules\Identity\Infrastructure\Persistence\Eloquent;

use App\Models\User as EloquentUser;
use App\Modules\Identity\Application\Contracts\UserRepository;
use App\Modules\Identity\Application\Exceptions\EmailAlreadyRegistered;
use App\Modules\Identity\Domain\Entities\User;
use App\Modules\Identity\Domain\Enums\UserStatus;
use App\Modules\Identity\Domain\ValueObjects\EmailAddress;
use App\Modules\Identity\Domain\ValueObjects\PasswordHash;
use App\Modules\Identity\Domain\ValueObjects\UserId;
use DateTimeImmutable;
use Illuminate\Database\QueryException;

final class EloquentUserRepository implements UserRepository
{
    public function add(User $user): void
    {
        $record = new EloquentUser();
        $record->forceFill($this->persistenceData($user) + [
            'id' => $user->id()->value(),
        ]);

        $this->persist($record);
    }

    public function save(User $user): void
    {
        $record = EloquentUser::query()->findOrFail($user->id()->value());
        $record->forceFill($this->persistenceData($user));

        $this->persist($record);
    }

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

    /**
     * @return array<string, mixed>
     */
    private function persistenceData(User $user): array
    {
        return [
            'name' => $user->name(),
            'email' => $user->email()->value(),
            'password' => $user->passwordHash()->value(),
            'status' => $user->status()->value,
            'email_verified_at' => $user->emailVerifiedAt(),
            'created_at' => $user->registeredAt(),
            'updated_at' => new DateTimeImmutable('now'),
        ];
    }

    private function persist(EloquentUser $record): void
    {
        try {
            $record->save();
        } catch (QueryException $exception) {
            if ($this->isEmailIdentityUniqueViolation($exception)) {
                throw EmailAlreadyRegistered::becauseEmailIsAlreadyRegistered($exception);
            }

            throw $exception;
        }
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

    private function isEmailIdentityUniqueViolation(QueryException $exception): bool
    {
        $sqlState = $exception->errorInfo[0] ?? null;
        $driverCode = (int) ($exception->errorInfo[1] ?? 0);
        $driverMessage = strtolower((string) ($exception->errorInfo[2] ?? ''));

        return match ($sqlState) {
            '23505' => str_contains($driverMessage, 'users_email_normalized_unique'),
            '23000' => in_array($driverCode, [19, 2067], true)
                && str_contains($driverMessage, 'users.email_normalized'),
            default => false,
        };
    }
}

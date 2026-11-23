<?php

declare(strict_types=1);

namespace App\Modules\Identity\Infrastructure\Persistence\Eloquent;

use App\Models\User as EloquentUser;
use App\Modules\Identity\Application\Contracts\UserRepository;
use App\Modules\Identity\Application\Exceptions\EmailAlreadyRegistered;
use App\Modules\Identity\Domain\Entities\User;
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

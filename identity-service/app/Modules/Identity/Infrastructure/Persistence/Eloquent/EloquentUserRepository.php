<?php

declare(strict_types=1);

namespace App\Modules\Identity\Infrastructure\Persistence\Eloquent;

use App\Models\User as EloquentUser;
use App\Modules\Identity\Application\Contracts\UserRepository;
use App\Modules\Identity\Application\Exceptions\EmailAlreadyRegistered;
use App\Modules\Identity\Domain\Entities\User;
use Illuminate\Database\QueryException;

final class EloquentUserRepository implements UserRepository
{
    public function save(User $user): void
    {
        try {
            (new EloquentUser())->forceFill([
                'id' => $user->id()->value(),
                'name' => $user->name(),
                'email' => $user->email()->value(),
                'password' => $user->passwordHash()->value(),
                'created_at' => $user->registeredAt(),
                'updated_at' => $user->registeredAt(),
            ])->save();
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
            // PostgreSQL: unique violation on the generated identity key.
            '23505' => str_contains($driverMessage, 'users_email_normalized_unique'),

            // SQLite is used by the fast local test suite.
            '23000' => in_array($driverCode, [19, 2067], true)
                && str_contains($driverMessage, 'users.email_normalized'),

            default => false,
        };
    }
}

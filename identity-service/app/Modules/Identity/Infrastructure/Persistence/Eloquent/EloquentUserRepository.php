<?php

declare(strict_types=1);

namespace App\Modules\Identity\Infrastructure\Persistence\Eloquent;

use App\Modules\Identity\Application\Contracts\UserRepository;
use App\Modules\Identity\Application\Exceptions\EmailAlreadyRegistered;
use App\Modules\Identity\Domain\Entities\User;
use Illuminate\Database\QueryException;

final class EloquentUserRepository implements UserRepository
{
    public function save(User $user): void
    {
        try {
            (new UserModel())->forceFill([
                'id' => $user->id()->value(),
                'name' => $user->name(),
                'email' => $user->email()->value(),
                'password' => $user->passwordHash()->value(),
                'created_at' => $user->registeredAt(),
                'updated_at' => $user->registeredAt(),
            ])->save();
        } catch (QueryException $exception) {
            if ($this->isEmailUniqueConstraintViolation($exception)) {
                throw EmailAlreadyRegistered::becauseEmailIsAlreadyRegistered($exception);
            }

            throw $exception;
        }
    }

    private function isEmailUniqueConstraintViolation(QueryException $exception): bool
    {
        $sqlState = $exception->errorInfo[0] ?? null;
        $driverCode = (int) ($exception->errorInfo[1] ?? 0);
        $message = strtolower($exception->getMessage());

        return match ($sqlState) {
            // PostgreSQL unique_violation for users_email_unique.
            '23505' => str_contains($message, 'users_email_unique'),

            // SQLite is used by the fast feature-test suite.
            '23000' => match ($driverCode) {
                19 => str_contains($message, 'unique constraint failed: users.email'),
                // MySQL duplicate entry for users_email_unique.
                1062 => str_contains($message, 'users_email_unique'),
                default => false,
            },

            default => false,
        };
    }
}

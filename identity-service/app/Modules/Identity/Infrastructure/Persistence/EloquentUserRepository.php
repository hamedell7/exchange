<?php

declare(strict_types=1);

namespace App\Modules\Identity\Infrastructure\Persistence;

use App\Models\User as UserModel;
use App\Modules\Identity\Application\Contracts\UserRepository;
use App\Modules\Identity\Domain\Entities\User;

final class EloquentUserRepository implements UserRepository
{
    public function save(User $user): void
    {
        (new UserModel())->forceFill([
            'id' => $user->id()->value(),
            'name' => $user->name(),
            'email' => $user->email()->value(),
            'password' => $user->passwordHash()->value(),
            'created_at' => $user->registeredAt(),
            'updated_at' => $user->registeredAt(),
        ])->save();
    }

    public function existsByEmail(string $email): bool
    {
        return UserModel::query()->where('email', $email)->exists();
    }
}

<?php

declare(strict_types=1);

namespace App\Modules\Identity\Application\UseCases;

use App\Modules\Identity\Application\Commands\RegisterUserCommand;
use App\Modules\Identity\Application\Contracts\PasswordHasher;
use App\Modules\Identity\Application\Contracts\UserIdGenerator;
use App\Modules\Identity\Application\Contracts\UserRepository;
use App\Modules\Identity\Application\Exceptions\EmailAlreadyRegistered;
use App\Modules\Identity\Domain\Entities\User;
use App\Modules\Identity\Domain\ValueObjects\EmailAddress;
use DateTimeImmutable;

final readonly class RegisterUser
{
    public function __construct(
        private UserRepository $users,
        private PasswordHasher $passwordHasher,
        private UserIdGenerator $userIds,
    ) {
    }

    public function execute(RegisterUserCommand $command): User
    {
        $email = new EmailAddress($command->email);

        if ($this->users->existsByEmail($email->value())) {
            throw EmailAlreadyRegistered::for($email->value());
        }

        $user = User::register(
            $this->userIds->generate(),
            $command->name,
            $email,
            $this->passwordHasher->hash($command->plainPassword),
            new DateTimeImmutable(),
        );

        $this->users->save($user);

        return $user;
    }
}

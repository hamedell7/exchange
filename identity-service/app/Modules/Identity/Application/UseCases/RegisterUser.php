<?php

declare(strict_types=1);

namespace App\Modules\Identity\Application\UseCases;

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

    public function execute(string $name, string $email, string $plainPassword): User
    {
        $emailAddress = new EmailAddress($email);

        if ($this->users->existsByEmail($emailAddress->value())) {
            throw EmailAlreadyRegistered::for($emailAddress->value());
        }

        $user = User::register(
            $this->userIds->generate(),
            trim($name),
            $emailAddress,
            $this->passwordHasher->hash($plainPassword),
            new DateTimeImmutable(),
        );

        $this->users->save($user);

        return $user;
    }
}

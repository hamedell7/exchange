<?php

declare(strict_types=1);

namespace App\Modules\Identity\Application\UseCases;

use App\Modules\Identity\Application\Commands\RegisterUserCommand;
use App\Modules\Identity\Application\Contracts\PasswordHasher;
use App\Modules\Identity\Application\Contracts\UserIdGenerator;
use App\Modules\Identity\Application\Contracts\UserRepository;
use App\Modules\Identity\Application\Results\RegisterUserResult;
use App\Modules\Identity\Domain\Entities\User;
use App\Modules\Identity\Domain\Policies\PasswordPolicy;
use App\Modules\Identity\Domain\ValueObjects\EmailAddress;
use DateTimeImmutable;

final readonly class RegisterUser
{
    public function __construct(
        private UserRepository $users,
        private PasswordHasher $passwordHasher,
        private UserIdGenerator $userIds,
        private PasswordPolicy $passwordPolicy,
    ) {
    }

    public function execute(RegisterUserCommand $command): RegisterUserResult
    {
        $email = new EmailAddress($command->email);

        $this->passwordPolicy->assertCompliant($command->plainPassword);

        $registeredAt = new DateTimeImmutable();

        $user = User::register(
            $this->userIds->generate(),
            $command->name,
            $email,
            $this->passwordHasher->hash($command->plainPassword),
            $registeredAt,
        );

        // The database unique constraint is the final authority for email
        // uniqueness; a pre-check cannot protect against concurrent requests.
        $this->users->save($user);

        return new RegisterUserResult(
            id: $user->id()->value(),
            name: $user->name(),
            email: $user->email()->value(),
            registeredAt: $user->registeredAt(),
        );
    }
}

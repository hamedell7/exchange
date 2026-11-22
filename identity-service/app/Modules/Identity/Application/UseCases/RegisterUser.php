<?php

declare(strict_types=1);

namespace App\Modules\Identity\Application\UseCases;

use App\Modules\Identity\Application\Commands\RegisterUserCommand;
use App\Modules\Identity\Application\Contracts\PasswordHasher;
use App\Modules\Identity\Application\Contracts\UserIdGenerator;
use App\Modules\Identity\Application\Contracts\UserRepository;
use App\Modules\Identity\Domain\Entities\User;
use App\Modules\Identity\Domain\ValueObjects\EmailAddress;
use DateTimeImmutable;
use InvalidArgumentException;

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
        if (mb_strlen($command->plainPassword) < RegisterUserCommand::MIN_PASSWORD_LENGTH) {
            throw new InvalidArgumentException(sprintf(
                'A password must contain at least %d characters.',
                RegisterUserCommand::MIN_PASSWORD_LENGTH,
            ));
        }

        $email = new EmailAddress($command->email);

        $user = User::register(
            $this->userIds->generate(),
            $command->name,
            $email,
            $this->passwordHasher->hash($command->plainPassword),
            new DateTimeImmutable(),
        );

        // The database unique constraint is the final authority for email
        // uniqueness; a pre-check cannot protect against concurrent requests.
        $this->users->save($user);

        return $user;
    }
}

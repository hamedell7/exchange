<?php

declare(strict_types=1);

namespace App\Modules\Identity\Application\UseCases;

use App\Modules\Identity\Application\Commands\RegisterUserCommand;
use App\Modules\Identity\Application\Contracts\AccountActionTokenRepository;
use App\Modules\Identity\Application\Contracts\Clock;
use App\Modules\Identity\Application\Contracts\IdentityMailer;
use App\Modules\Identity\Application\Contracts\PasswordHasher;
use App\Modules\Identity\Application\Contracts\TokenGenerator;
use App\Modules\Identity\Application\Contracts\TransactionRunner;
use App\Modules\Identity\Application\Contracts\UserIdGenerator;
use App\Modules\Identity\Application\Contracts\UserRepository;
use App\Modules\Identity\Application\Enums\AccountAction;
use App\Modules\Identity\Application\Exceptions\InvalidRegistrationInput;
use App\Modules\Identity\Domain\Entities\User;
use App\Modules\Identity\Domain\ValueObjects\EmailAddress;
use InvalidArgumentException;

final readonly class RegisterUser
{
    public function __construct(
        private UserRepository $users,
        private PasswordHasher $passwordHasher,
        private UserIdGenerator $userIds,
        private AccountActionTokenRepository $tokens,
        private TokenGenerator $tokenGenerator,
        private IdentityMailer $mailer,
        private Clock $clock,
        private TransactionRunner $transactions,
    ) {
    }

    /**
     * @throws InvalidRegistrationInput When a registration field is invalid.
     */
    public function execute(RegisterUserCommand $command): User
    {
        $name = trim($command->name);
        $nameLength = mb_strlen($name, 'UTF-8');

        if ($nameLength < RegisterUserCommand::MIN_NAME_LENGTH) {
            throw new InvalidRegistrationInput(
                'name',
                sprintf('The name must contain at least %d characters.', RegisterUserCommand::MIN_NAME_LENGTH),
            );
        }

        if ($nameLength > RegisterUserCommand::MAX_NAME_LENGTH) {
            throw new InvalidRegistrationInput(
                'name',
                sprintf('The name may not exceed %d characters.', RegisterUserCommand::MAX_NAME_LENGTH),
            );
        }

        $passwordLength = mb_strlen($command->plainPassword, 'UTF-8');

        if ($passwordLength < RegisterUserCommand::MIN_PASSWORD_LENGTH) {
            throw new InvalidRegistrationInput(
                'password',
                sprintf('The password must contain at least %d characters.', RegisterUserCommand::MIN_PASSWORD_LENGTH),
            );
        }

        if ($passwordLength > RegisterUserCommand::MAX_PASSWORD_LENGTH) {
            throw new InvalidRegistrationInput(
                'password',
                sprintf('The password may not exceed %d characters.', RegisterUserCommand::MAX_PASSWORD_LENGTH),
            );
        }

        try {
            $email = new EmailAddress($command->email);
        } catch (InvalidArgumentException $exception) {
            throw new InvalidRegistrationInput(
                'email',
                'Please provide a valid email address.',
                $exception,
            );
        }

        $now = $this->clock->now();
        $user = User::register(
            $this->userIds->generate(),
            $name,
            $email,
            $this->passwordHasher->hash($command->plainPassword),
            $now,
        );
        $plainToken = $this->tokenGenerator->generate();

        $this->transactions->run(function () use ($user, $plainToken, $now): void {
            $this->users->add($user);
            $this->tokens->issue(
                $user->id(),
                AccountAction::VerifyEmail,
                hash('sha256', $plainToken),
                $now->modify('+60 minutes'),
                $now,
            );
        });

        // Email is sent only after the account and its one-time token commit.
        // A mail transport failure is recoverable through the resend endpoint.
        $this->mailer->sendEmailVerification($email, $plainToken);

        return $user;
    }
}

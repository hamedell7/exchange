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
use App\Modules\Identity\Domain\Policies\PasswordPolicy;
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
        try {
            PasswordPolicy::validate($command->plainPassword);
        } catch (InvalidArgumentException $exception) {
            throw new InvalidRegistrationInput('password', $exception->getMessage(), $exception);
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
        $userId = $this->userIds->generate();
        $passwordHash = $this->passwordHasher->hash($command->plainPassword);

        try {
            $user = User::register(
                $userId,
                $command->name,
                $email,
                $passwordHash,
                $now,
            );
        } catch (InvalidArgumentException $exception) {
            throw new InvalidRegistrationInput('name', $exception->getMessage(), $exception);
        }

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

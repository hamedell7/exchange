<?php

declare(strict_types=1);

namespace App\Modules\Identity\Application\UseCases;

use App\Modules\Identity\Application\Commands\ResetPasswordCommand;
use App\Modules\Identity\Application\Commands\RegisterUserCommand;
use App\Modules\Identity\Application\Contracts\AccountActionTokenRepository;
use App\Modules\Identity\Application\Contracts\Clock;
use App\Modules\Identity\Application\Contracts\PasswordHasher;
use App\Modules\Identity\Application\Contracts\TransactionRunner;
use App\Modules\Identity\Application\Contracts\UserRepository;
use App\Modules\Identity\Application\Enums\AccountAction;
use App\Modules\Identity\Application\Exceptions\InvalidAccountActionToken;
use App\Modules\Identity\Application\Exceptions\InvalidRegistrationInput;
use App\Modules\Identity\Domain\Enums\UserStatus;

final readonly class ResetPassword
{
    public function __construct(
        private AccountActionTokenRepository $tokens,
        private UserRepository $users,
        private PasswordHasher $passwordHasher,
        private Clock $clock,
        private TransactionRunner $transactions,
    ) {
    }

    public function execute(ResetPasswordCommand $command): void
    {
        $length = mb_strlen($command->plainPassword, 'UTF-8');

        if ($length < RegisterUserCommand::MIN_PASSWORD_LENGTH) {
            throw new InvalidRegistrationInput(
                'password',
                sprintf('The password must contain at least %d characters.', RegisterUserCommand::MIN_PASSWORD_LENGTH),
            );
        }

        if ($length > RegisterUserCommand::MAX_PASSWORD_LENGTH) {
            throw new InvalidRegistrationInput(
                'password',
                sprintf('The password may not exceed %d characters.', RegisterUserCommand::MAX_PASSWORD_LENGTH),
            );
        }

        $now = $this->clock->now();

        $this->transactions->run(function () use ($command, $now): void {
            $userId = $this->tokens->consume(
                AccountAction::ResetPassword,
                hash('sha256', $command->token),
                $now,
            );

            if ($userId === null) {
                throw InvalidAccountActionToken::expiredOrInvalid();
            }

            $user = $this->users->findById($userId);

            if ($user === null || $user->status() === UserStatus::Closed) {
                throw InvalidAccountActionToken::expiredOrInvalid();
            }

            $user->changePassword($this->passwordHasher->hash($command->plainPassword));
            $this->users->save($user);
        });
    }
}

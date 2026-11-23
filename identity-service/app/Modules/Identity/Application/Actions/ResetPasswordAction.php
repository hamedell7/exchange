<?php

declare(strict_types=1);

namespace App\Modules\Identity\Application\Actions;

use App\Modules\Identity\Application\Commands\ResetPasswordCommand;
use App\Modules\Identity\Application\Contracts\AccountActionTokenRepository;
use App\Modules\Identity\Application\Contracts\Clock;
use App\Modules\Identity\Application\Contracts\PasswordHasher;
use App\Modules\Identity\Application\Contracts\TransactionRunner;
use App\Modules\Identity\Application\Contracts\UserReader;
use App\Modules\Identity\Application\Contracts\UserRepository;
use App\Modules\Identity\Application\Enums\AccountAction;
use App\Modules\Identity\Application\Exceptions\InvalidAccountActionToken;
use App\Modules\Identity\Application\Exceptions\InvalidRegistrationInput;
use App\Modules\Identity\Domain\Enums\UserStatus;
use App\Modules\Identity\Domain\Policies\PasswordPolicy;
use InvalidArgumentException;

final readonly class ResetPasswordAction
{
    public function __construct(
        private AccountActionTokenRepository $tokens,
        private UserReader $userReader,
        private UserRepository $users,
        private PasswordHasher $passwordHasher,
        private Clock $clock,
        private TransactionRunner $transactions,
    ) {
    }

    public function execute(ResetPasswordCommand $command): void
    {
        try {
            PasswordPolicy::validate($command->plainPassword);
        } catch (InvalidArgumentException $exception) {
            throw new InvalidRegistrationInput('password', $exception->getMessage(), $exception);
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

            $user = $this->userReader->findById($userId);

            if ($user === null || $user->status() === UserStatus::Closed) {
                throw InvalidAccountActionToken::expiredOrInvalid();
            }

            $user->changePassword($this->passwordHasher->hash($command->plainPassword));
            $this->users->save($user);
        });
    }
}

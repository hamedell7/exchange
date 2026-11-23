<?php

declare(strict_types=1);

namespace App\Modules\Identity\Application\Actions;

use App\Modules\Identity\Application\Commands\VerifyEmailCommand;
use App\Modules\Identity\Application\Contracts\AccountActionTokenRepository;
use App\Modules\Identity\Application\Contracts\Clock;
use App\Modules\Identity\Application\Contracts\TransactionRunner;
use App\Modules\Identity\Application\Contracts\UserReader;
use App\Modules\Identity\Application\Contracts\UserRepository;
use App\Modules\Identity\Application\Enums\AccountAction;
use App\Modules\Identity\Application\Exceptions\InvalidAccountActionToken;
use App\Modules\Identity\Domain\Enums\UserStatus;
use App\Modules\Identity\Domain\Entities\User;

final readonly class VerifyEmailAction
{
    public function __construct(
        private AccountActionTokenRepository $tokens,
        private UserReader $userReader,
        private UserRepository $users,
        private Clock $clock,
        private TransactionRunner $transactions,
    ) {
    }

    public function execute(VerifyEmailCommand $command): User
    {
        $now = $this->clock->now();

        return $this->transactions->run(function () use ($command, $now): User {
            $userId = $this->tokens->consume(
                AccountAction::VerifyEmail,
                hash('sha256', $command->token),
                $now,
            );

            if ($userId === null) {
                throw InvalidAccountActionToken::expiredOrInvalid();
            }

            $user = $this->userReader->findById($userId);

            if ($user === null || $user->status() !== UserStatus::PendingVerification) {
                throw InvalidAccountActionToken::expiredOrInvalid();
            }

            $user->verifyEmail($now);
            $this->users->save($user);

            return $user;
        });
    }
}

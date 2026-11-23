<?php

declare(strict_types=1);

namespace App\Modules\Identity\Application\Actions;

use App\Modules\Identity\Application\Commands\RequestPasswordResetCommand;
use App\Modules\Identity\Application\Contracts\AccountActionTokenRepository;
use App\Modules\Identity\Application\Contracts\Clock;
use App\Modules\Identity\Application\Contracts\IdentityMailer;
use App\Modules\Identity\Application\Contracts\TokenGenerator;
use App\Modules\Identity\Application\Contracts\TransactionRunner;
use App\Modules\Identity\Application\Contracts\UserReader;
use App\Modules\Identity\Application\Enums\AccountAction;
use App\Modules\Identity\Domain\Enums\UserStatus;
use App\Modules\Identity\Domain\ValueObjects\EmailAddress;

final readonly class RequestPasswordResetAction
{
    public function __construct(
        private UserReader $users,
        private AccountActionTokenRepository $tokens,
        private TokenGenerator $tokenGenerator,
        private IdentityMailer $mailer,
        private Clock $clock,
        private TransactionRunner $transactions,
    ) {
    }

    public function execute(RequestPasswordResetCommand $command): void
    {
        $email = new EmailAddress($command->email);
        $user = $this->users->findByEmail($email);

        // The same successful response is returned for unknown addresses.
        if ($user === null || $user->status() === UserStatus::Closed) {
            return;
        }

        $now = $this->clock->now();
        $plainToken = $this->tokenGenerator->generate();

        $this->transactions->run(fn () => $this->tokens->issue(
            $user->id(),
            AccountAction::ResetPassword,
            hash('sha256', $plainToken),
            $now->modify('+30 minutes'),
            $now,
        ));

        $this->mailer->sendPasswordReset($email, $plainToken);
    }
}

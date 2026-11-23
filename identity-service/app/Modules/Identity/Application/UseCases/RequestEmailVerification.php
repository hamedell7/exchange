<?php

declare(strict_types=1);

namespace App\Modules\Identity\Application\UseCases;

use App\Modules\Identity\Application\Commands\RequestEmailVerificationCommand;
use App\Modules\Identity\Application\Contracts\AccountActionTokenRepository;
use App\Modules\Identity\Application\Contracts\Clock;
use App\Modules\Identity\Application\Contracts\IdentityMailer;
use App\Modules\Identity\Application\Contracts\TokenGenerator;
use App\Modules\Identity\Application\Contracts\TransactionRunner;
use App\Modules\Identity\Application\Contracts\UserRepository;
use App\Modules\Identity\Application\Enums\AccountAction;
use App\Modules\Identity\Domain\Enums\UserStatus;
use App\Modules\Identity\Domain\ValueObjects\EmailAddress;

final readonly class RequestEmailVerification
{
    public function __construct(
        private UserRepository $users,
        private AccountActionTokenRepository $tokens,
        private TokenGenerator $tokenGenerator,
        private IdentityMailer $mailer,
        private Clock $clock,
        private TransactionRunner $transactions,
    ) {
    }

    public function execute(RequestEmailVerificationCommand $command): void
    {
        $email = new EmailAddress($command->email);
        $user = $this->users->findByEmail($email);

        // Keep the response indistinguishable for unknown and already verified
        // addresses so this endpoint cannot be used to enumerate accounts.
        if ($user === null || $user->status() !== UserStatus::PendingVerification) {
            return;
        }

        $now = $this->clock->now();
        $plainToken = $this->tokenGenerator->generate();

        $this->transactions->run(fn () => $this->tokens->issue(
            $user->id(),
            AccountAction::VerifyEmail,
            hash('sha256', $plainToken),
            $now->modify('+60 minutes'),
            $now,
        ));

        $this->mailer->sendEmailVerification($email, $plainToken);
    }
}

<?php

declare(strict_types=1);

namespace App\Services\Identity;

use App\Enums\Identity\AccountAction;
use App\Enums\Identity\UserStatus;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class EmailVerificationService
{
    public function __construct(
        private readonly AccountActionTokenService $tokens,
        private readonly IdentityMailService $mail,
    ) {
    }

    public function verify(string $plainToken): User
    {
        return DB::transaction(function () use ($plainToken): User {
            $userId = $this->tokens->consume(AccountAction::VerifyEmail, $plainToken);

            $user = $userId === null
                ? null
                : User::query()->whereKey($userId)->lockForUpdate()->first();

            if ($user === null || $user->status !== UserStatus::PendingVerification) {
                throw ValidationException::withMessages([
                    'token' => ['The account action token is invalid or has expired.'],
                ]);
            }

            $user->forceFill([
                'status' => UserStatus::Active,
                'email_verified_at' => now(),
            ])->save();

            return $user;
        });
    }

    public function resend(string $email): void
    {
        $user = User::query()
            ->where('email_normalized', strtolower(trim($email)))
            ->first();

        // Keep the response indistinguishable for unknown and verified addresses.
        if ($user === null || $user->status !== UserStatus::PendingVerification) {
            return;
        }

        $plainToken = DB::transaction(fn (): string => $this->tokens->issue(
            (string) $user->getKey(),
            AccountAction::VerifyEmail,
            60,
        ));

        $this->mail->sendEmailVerification((string) $user->email, $plainToken);
    }
}

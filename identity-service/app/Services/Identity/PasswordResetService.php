<?php

declare(strict_types=1);

namespace App\Services\Identity;

use App\Enums\Identity\AccountAction;
use App\Enums\Identity\UserStatus;
use App\Models\User;
use App\Support\Identity\PasswordPolicy;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

final class PasswordResetService
{
    public function __construct(
        private readonly AccountActionTokenService $tokens,
        private readonly IdentityMailService $mail,
    ) {
    }

    public function request(string $email): void
    {
        $user = User::query()
            ->where('email_normalized', strtolower(trim($email)))
            ->first();

        // The same successful response is returned for unknown addresses.
        if ($user === null || $user->status === UserStatus::Closed) {
            return;
        }

        $plainToken = DB::transaction(fn (): string => $this->tokens->issue(
            (string) $user->getKey(),
            AccountAction::ResetPassword,
            30,
        ));

        $this->mail->sendPasswordReset((string) $user->email, $plainToken);
    }

    public function reset(string $plainToken, string $plainPassword): void
    {
        PasswordPolicy::validate($plainPassword);

        DB::transaction(function () use ($plainToken, $plainPassword): void {
            $userId = $this->tokens->consume(AccountAction::ResetPassword, $plainToken);

            $user = $userId === null
                ? null
                : User::query()->whereKey($userId)->lockForUpdate()->first();

            if ($user === null || $user->status === UserStatus::Closed) {
                throw ValidationException::withMessages([
                    'token' => ['The account action token is invalid or has expired.'],
                ]);
            }

            $user->forceFill([
                'password' => Hash::make($plainPassword),
            ])->save();

            // Password reset invalidates every previously issued access token.
            $user->tokens()->delete();
        });
    }
}

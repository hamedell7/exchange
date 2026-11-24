<?php

declare(strict_types=1);

namespace App\Services\Identity;

use App\Enums\Identity\AccountAction;
use App\Enums\Identity\UserStatus;
use App\Exceptions\Identity\EmailAlreadyRegistered;
use App\Models\User;
use App\Support\Identity\PasswordPolicy;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

final class UserRegistrationService
{
    public function __construct(
        private readonly AccountActionTokenService $tokens,
        private readonly IdentityMailService $mail,
    ) {
    }

    public function register(string $name, string $email, string $plainPassword): User
    {
        $name = trim($name);
        $nameLength = mb_strlen($name, 'UTF-8');

        if ($nameLength < User::MIN_NAME_LENGTH || $nameLength > User::MAX_NAME_LENGTH) {
            throw ValidationException::withMessages([
                'name' => [
                    sprintf(
                        'The name must contain between %d and %d characters.',
                        User::MIN_NAME_LENGTH,
                        User::MAX_NAME_LENGTH,
                    ),
                ],
            ]);
        }

        PasswordPolicy::validate($plainPassword);

        $email = trim($email);
        $separator = strrpos($email, '@');
        $email = substr($email, 0, $separator).'@'.strtolower(substr($email, $separator + 1));

        try {
            [$user, $plainToken] = DB::transaction(function () use ($name, $email, $plainPassword): array {
                $user = new User();
                $user->forceFill([
                    'name' => $name,
                    'email' => $email,
                    'password' => Hash::make($plainPassword),
                    'status' => UserStatus::PendingVerification,
                ]);
                $user->save();

                $plainToken = $this->tokens->issue(
                    (string) $user->getKey(),
                    AccountAction::VerifyEmail,
                    60,
                );

                return [$user, $plainToken];
            });
        } catch (QueryException $exception) {
            if ($this->isEmailIdentityUniqueViolation($exception)) {
                throw new EmailAlreadyRegistered($exception);
            }

            throw $exception;
        }

        // Send only after the account and its one-time token have committed.
        $this->mail->sendEmailVerification((string) $user->email, $plainToken);

        return $user;
    }

    private function isEmailIdentityUniqueViolation(QueryException $exception): bool
    {
        $sqlState = $exception->errorInfo[0] ?? null;
        $driverCode = (int) ($exception->errorInfo[1] ?? 0);
        $driverMessage = strtolower((string) ($exception->errorInfo[2] ?? ''));

        return match ($sqlState) {
            '23505' => str_contains($driverMessage, 'users_email_normalized_unique'),
            '23000' => in_array($driverCode, [19, 2067], true)
                && str_contains($driverMessage, 'users.email_normalized'),
            default => false,
        };
    }
}

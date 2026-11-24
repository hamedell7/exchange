<?php

declare(strict_types=1);

namespace App\Services\Identity;

use App\Enums\Identity\UserStatus;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Carbon;

final class LoginService
{
    /**
     * Authenticate by the normalized email identity and issue a Sanctum token.
     *
     * Accounts awaiting legacy email verification remain eligible to sign in.
     * Suspended and closed accounts are always rejected.
     *
     * @return array{user: User, access_token: string, expires_at: Carbon}|null
     */
    public function authenticate(
        string $email,
        string $plainPassword,
        ?string $deviceName = null,
    ): ?array {
        $normalizedEmail = strtolower(trim($email));

        $user = User::query()
            ->where('email_normalized', $normalizedEmail)
            ->first();

        if (
            $user === null
            || ! Hash::check($plainPassword, $user->password)
            || in_array($user->status, [UserStatus::Suspended, UserStatus::Closed], true)
        ) {
            return null;
        }

        $lifetimeMinutes = max(1, (int) config('identity.access_token_lifetime_minutes', 43200));
        $expiresAt = now()->addMinutes($lifetimeMinutes);
        $tokenName = is_string($deviceName) && trim($deviceName) !== ''
            ? mb_substr(trim($deviceName), 0, 100)
            : 'api-client';

        $newAccessToken = $user->createToken(
            $tokenName,
            ['identity:read', 'identity:write'],
            $expiresAt,
        );

        return [
            'user' => $user,
            'access_token' => $newAccessToken->plainTextToken,
            'expires_at' => $expiresAt,
        ];
    }
}

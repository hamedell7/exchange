<?php

declare(strict_types=1);

namespace App\Services\Identity;

use App\Enums\Identity\AccountAction;
use Illuminate\Support\Facades\DB;

final class AccountActionTokenService
{
    /**
     * Issue a new token and invalidate any outstanding token for the same
     * user and purpose. Call this inside the caller's database transaction.
     */
    public function issue(string $userId, AccountAction $action, int $lifetimeMinutes): string
    {
        $issuedAt = now();
        $plainToken = bin2hex(random_bytes(32));

        DB::table('identity_action_tokens')
            ->where('user_id', $userId)
            ->where('purpose', $action->value)
            ->whereNull('consumed_at')
            ->update(['consumed_at' => $issuedAt]);

        DB::table('identity_action_tokens')->insert([
            'user_id' => $userId,
            'purpose' => $action->value,
            'token_hash' => hash('sha256', $plainToken),
            'expires_at' => $issuedAt->copy()->addMinutes($lifetimeMinutes),
            'consumed_at' => null,
            'created_at' => $issuedAt,
        ]);

        return $plainToken;
    }

    /**
     * Consume a valid token once. Call this inside the same transaction as
     * the account update so a failed update rolls token consumption back.
     */
    public function consume(AccountAction $action, string $plainToken): ?string
    {
        $consumedAt = now();

        $token = DB::table('identity_action_tokens')
            ->where('purpose', $action->value)
            ->where('token_hash', hash('sha256', $plainToken))
            ->whereNull('consumed_at')
            ->where('expires_at', '>', $consumedAt)
            ->lockForUpdate()
            ->first();

        if ($token === null) {
            return null;
        }

        $affected = DB::table('identity_action_tokens')
            ->where('id', $token->id)
            ->whereNull('consumed_at')
            ->where('expires_at', '>', $consumedAt)
            ->update(['consumed_at' => $consumedAt]);

        return $affected === 1 ? (string) $token->user_id : null;
    }
}

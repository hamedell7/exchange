<?php

declare(strict_types=1);

namespace App\Modules\Identity\Infrastructure\Persistence\Eloquent;

use App\Modules\Identity\Application\Contracts\AccountActionTokenRepository;
use App\Modules\Identity\Application\Enums\AccountAction;
use App\Modules\Identity\Domain\ValueObjects\UserId;
use DateTimeImmutable;
use Illuminate\Support\Facades\DB;

final class EloquentAccountActionTokenRepository implements AccountActionTokenRepository
{
    public function issue(
        UserId $userId,
        AccountAction $action,
        string $tokenHash,
        DateTimeImmutable $expiresAt,
        DateTimeImmutable $issuedAt,
    ): void {
        // Revoke the prior outstanding token for this account and purpose,
        // retaining its consumed_at timestamp for operational audit history.
        DB::table('identity_action_tokens')
            ->where('user_id', $userId->value())
            ->where('purpose', $action->value)
            ->whereNull('consumed_at')
            ->update(['consumed_at' => $issuedAt]);

        DB::table('identity_action_tokens')->insert([
            'user_id' => $userId->value(),
            'purpose' => $action->value,
            'token_hash' => $tokenHash,
            'expires_at' => $expiresAt,
            'consumed_at' => null,
            'created_at' => $issuedAt,
        ]);
    }

    public function consume(
        AccountAction $action,
        string $tokenHash,
        DateTimeImmutable $consumedAt,
    ): ?UserId {
        $token = DB::table('identity_action_tokens')
            ->where('purpose', $action->value)
            ->where('token_hash', $tokenHash)
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

        return $affected === 1 ? new UserId((string) $token->user_id) : null;
    }
}

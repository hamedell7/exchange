<?php

declare(strict_types=1);

namespace App\Modules\Identity\Application\Contracts;

use App\Modules\Identity\Application\Enums\AccountAction;
use App\Modules\Identity\Domain\ValueObjects\UserId;
use DateTimeImmutable;

interface AccountActionTokenRepository
{
    public function issue(
        UserId $userId,
        AccountAction $action,
        string $tokenHash,
        DateTimeImmutable $expiresAt,
        DateTimeImmutable $issuedAt,
    ): void;

    /**
     * Atomically consumes an unexpired token. The caller must execute this
     * operation and the corresponding aggregate update in one transaction.
     */
    public function consume(
        AccountAction $action,
        string $tokenHash,
        DateTimeImmutable $consumedAt,
    ): ?UserId;
}

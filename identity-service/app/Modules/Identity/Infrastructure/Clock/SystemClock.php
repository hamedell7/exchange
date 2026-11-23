<?php

declare(strict_types=1);

namespace App\Modules\Identity\Infrastructure\Clock;

use App\Modules\Identity\Application\Contracts\Clock;
use DateTimeImmutable;

final class SystemClock implements Clock
{
    public function now(): DateTimeImmutable
    {
        return new DateTimeImmutable('now');
    }
}

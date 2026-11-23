<?php

declare(strict_types=1);

namespace App\Modules\Identity\Application\Commands;

final readonly class VerifyEmailCommand
{
    public function __construct(public string $token)
    {
    }
}

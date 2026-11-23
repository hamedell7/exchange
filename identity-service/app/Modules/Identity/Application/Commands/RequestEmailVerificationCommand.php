<?php

declare(strict_types=1);

namespace App\Modules\Identity\Application\Commands;

final readonly class RequestEmailVerificationCommand
{
    public function __construct(public string $email)
    {
    }
}

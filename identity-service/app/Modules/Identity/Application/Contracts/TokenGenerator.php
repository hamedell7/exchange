<?php

declare(strict_types=1);

namespace App\Modules\Identity\Application\Contracts;

interface TokenGenerator
{
    /** Returns a cryptographically secure, URL-safe, 256-bit token. */
    public function generate(): string;
}

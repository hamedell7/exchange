<?php

declare(strict_types=1);

namespace App\Modules\Identity\Application\Contracts;

interface TransactionRunner
{
    /** @template T @param callable(): T $operation @return T */
    public function run(callable $operation): mixed;
}

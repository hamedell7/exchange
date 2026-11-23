<?php

declare(strict_types=1);

namespace App\Modules\Identity\Infrastructure\Database;

use App\Modules\Identity\Application\Contracts\TransactionRunner;
use Illuminate\Support\Facades\DB;

final class LaravelTransactionRunner implements TransactionRunner
{
    public function run(callable $operation): mixed
    {
        return DB::transaction($operation);
    }
}

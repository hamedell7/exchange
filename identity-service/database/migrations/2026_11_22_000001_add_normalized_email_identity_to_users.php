<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Do not silently merge or discard existing accounts. Stop before
        // changing schema if legacy case-sensitive duplicates are present.
        $hasNormalizedDuplicates = DB::table('users')
            ->selectRaw('lower(email)')
            ->groupByRaw('lower(email)')
            ->havingRaw('COUNT(*) > 1')
            ->exists();

        if ($hasNormalizedDuplicates) {
            throw new RuntimeException(
                'Cannot enforce case-insensitive email identity: resolve duplicate normalized emails before retrying this migration.',
            );
        }

        $driver = DB::connection()->getDriverName();

        Schema::table('users', function (Blueprint $table) use ($driver): void {
            // Replace case-sensitive email uniqueness with one normalized
            // identity key. SQLite only allows VIRTUAL generated columns to
            // be added to an existing table; PostgreSQL uses STORED columns.
            $table->dropUnique('users_email_unique');

            $column = $table->string('email_normalized');

            if ($driver === 'sqlite') {
                $column->virtualAs('lower(email)');
            } else {
                $column->storedAs('lower(email)');
            }

            $table->unique('email_normalized', 'users_email_normalized_unique');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->dropUnique('users_email_normalized_unique');
            $table->dropColumn('email_normalized');
            $table->unique('email', 'users_email_unique');
        });
    }
};

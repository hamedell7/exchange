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
        Schema::table('users', function (Blueprint $table): void {
            $table->string('status', 32)->default('active')->after('email');
        });

        // Preserve the meaning of legacy rows: an email that was never
        // verified must not become an active Identity account by migration.
        DB::table('users')
            ->whereNull('email_verified_at')
            ->update(['status' => 'pending_verification']);

        DB::table('users')
            ->whereNotNull('email_verified_at')
            ->update(['status' => 'active']);

        Schema::create('identity_action_tokens', function (Blueprint $table): void {
            $table->id();
            $table->foreignUlid('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('purpose', 32);
            $table->char('token_hash', 64)->unique();
            $table->timestamp('expires_at');
            $table->timestamp('consumed_at')->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->index(
                ['user_id', 'purpose', 'consumed_at'],
                'identity_action_tokens_user_purpose_consumed_index',
            );
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('identity_action_tokens');

        Schema::table('users', function (Blueprint $table): void {
            $table->dropColumn('status');
        });
    }
};

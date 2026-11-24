<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('market_assets', function (Blueprint $table): void {
            $table->id();
            $table->string('provider', 32);
            $table->unsignedBigInteger('provider_asset_id');
            $table->string('name', 160);
            $table->string('symbol', 40);
            $table->string('slug', 180);
            $table->text('logo_url')->nullable();
            $table->text('description')->nullable();
            $table->string('website_url', 2048)->nullable();
            $table->json('tags')->nullable();
            $table->json('platform')->nullable();
            $table->unsignedInteger('market_cap_rank')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestampTz('date_added')->nullable();
            $table->timestampTz('metadata_synced_at')->nullable();
            $table->timestampsTz();

            $table->unique(['provider', 'provider_asset_id'], 'market_assets_provider_asset_unique');
            $table->index('market_cap_rank', 'market_assets_market_cap_rank_index');
            $table->index('symbol', 'market_assets_symbol_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('market_assets');
    }
};

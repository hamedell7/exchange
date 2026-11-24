<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('market_asset_quotes', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('market_asset_id')->constrained('market_assets')->cascadeOnDelete();
            $table->string('quote_currency', 12)->default('USDT');
            $table->decimal('price', 36, 24);
            $table->decimal('market_cap', 36, 8)->nullable();
            $table->decimal('volume_24h', 36, 8)->nullable();
            $table->decimal('percent_change_1h', 16, 8)->nullable();
            $table->decimal('percent_change_24h', 16, 8)->nullable();
            $table->decimal('percent_change_7d', 16, 8)->nullable();
            $table->string('provider', 32);
            $table->timestampTz('source_updated_at')->nullable();
            $table->timestampTz('fetched_at');
            $table->timestampsTz();

            $table->unique(['market_asset_id', 'quote_currency'], 'market_asset_quotes_asset_currency_unique');
            $table->index('fetched_at', 'market_asset_quotes_fetched_at_index');
        });

        Schema::create('market_exchange_rates', function (Blueprint $table): void {
            $table->id();
            $table->string('base_currency', 12);
            $table->string('quote_currency', 12);
            $table->decimal('rate', 36, 12);
            $table->string('provider', 32);
            $table->string('provider_pair', 32);
            $table->timestampTz('fetched_at');
            $table->timestampsTz();

            $table->unique(['base_currency', 'quote_currency'], 'market_exchange_rates_pair_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('market_exchange_rates');
        Schema::dropIfExists('market_asset_quotes');
    }
};

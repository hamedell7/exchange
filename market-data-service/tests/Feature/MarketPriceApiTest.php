<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\MarketAsset;
use App\Models\MarketAssetQuote;
use App\Models\MarketExchangeRate;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class MarketPriceApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_public_asset_index_includes_usdt_and_derived_toman_prices(): void
    {
        $asset = MarketAsset::query()->create([
            'provider' => 'coinmarketcap',
            'provider_asset_id' => 1,
            'name' => 'Bitcoin',
            'symbol' => 'BTC',
            'slug' => 'bitcoin',
            'market_cap_rank' => 1,
            'is_active' => true,
        ]);

        MarketAssetQuote::query()->create([
            'market_asset_id' => $asset->id,
            'quote_currency' => 'USDT',
            'price' => '2.500000000000000000000000',
            'percent_change_24h' => '1.25000000',
            'provider' => 'coinmarketcap',
            'fetched_at' => now(),
        ]);

        MarketExchangeRate::query()->create([
            'base_currency' => 'USDT',
            'quote_currency' => 'TOMAN',
            'rate' => '95000.000000000000',
            'provider' => 'nobitex',
            'provider_pair' => 'usdt-rls',
            'fetched_at' => now(),
        ]);

        $this->getJson('/api/v1/market/assets')
            ->assertOk()
            ->assertJsonPath('data.0.price_usdt', '2.500000000000000000000000')
            ->assertJsonPath('data.0.price_toman', '237500.00000000')
            ->assertJsonPath('data.0.percent_change_24h', '1.25000000')
            ->assertJsonPath('market_data.usdt_toman_rate', '95000.000000000000');
    }
}

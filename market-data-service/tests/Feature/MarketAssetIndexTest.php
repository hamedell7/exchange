<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\MarketAsset;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class MarketAssetIndexTest extends TestCase
{
    use RefreshDatabase;

    public function test_public_asset_index_returns_ranked_assets_without_authentication(): void
    {
        MarketAsset::query()->create([
            'provider' => 'coinmarketcap', 'provider_asset_id' => 1027,
            'name' => 'Ethereum', 'symbol' => 'ETH', 'slug' => 'ethereum',
            'market_cap_rank' => 2, 'is_active' => true,
        ]);

        MarketAsset::query()->create([
            'provider' => 'coinmarketcap', 'provider_asset_id' => 1,
            'name' => 'Bitcoin', 'symbol' => 'BTC', 'slug' => 'bitcoin',
            'market_cap_rank' => 1, 'is_active' => true,
        ]);

        $this->getJson('/api/v1/market/assets')
            ->assertOk()
            ->assertJsonPath('data.0.symbol', 'BTC')
            ->assertJsonPath('data.1.symbol', 'ETH');
    }

    public function test_asset_index_can_search_by_name_or_symbol(): void
    {
        MarketAsset::query()->create([
            'provider' => 'coinmarketcap', 'provider_asset_id' => 1,
            'name' => 'Bitcoin', 'symbol' => 'BTC', 'slug' => 'bitcoin',
            'market_cap_rank' => 1, 'is_active' => true,
        ]);

        $this->getJson('/api/v1/market/assets?search=btc')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.name', 'Bitcoin');
    }

    public function test_asset_index_rejects_invalid_page_size(): void
    {
        $this->getJson('/api/v1/market/assets?per_page=251')
            ->assertUnprocessable()
            ->assertJsonValidationErrors('per_page');
    }
}

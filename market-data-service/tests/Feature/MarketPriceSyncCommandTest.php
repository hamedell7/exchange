<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\MarketAsset;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

final class MarketPriceSyncCommandTest extends TestCase
{
    use RefreshDatabase;

    public function test_command_synchronizes_nobitex_prices_and_the_usdt_toman_exchange_rate(): void
    {
        $bitcoin = MarketAsset::query()->create([
            'provider' => 'coinmarketcap',
            'provider_asset_id' => 1,
            'name' => 'Bitcoin',
            'symbol' => 'BTC',
            'slug' => 'bitcoin',
            'market_cap_rank' => 1,
            'is_active' => true,
        ]);

        $ethereum = MarketAsset::query()->create([
            'provider' => 'coinmarketcap',
            'provider_asset_id' => 2,
            'name' => 'Ethereum',
            'symbol' => 'ETH',
            'slug' => 'ethereum',
            'market_cap_rank' => 2,
            'is_active' => true,
        ]);

        Http::fake([
            '*market/stats*' => Http::response([
                'status' => 'ok',
                'stats' => [
                    'btc-rls' => [
                        'latest' => '61750237500',
                        'dayOpen' => '60000000000',
                        'dayChange' => '2.9167',
                        'volumeDst' => '6175023750000',
                    ],
                    'eth-usdt' => [
                        'latest' => '3200.50',
                        'dayChange' => '-0.5',
                        'volumeDst' => '150000',
                    ],
                    'usdt-rls' => [
                        'latest' => '950000',
                        'dayOpen' => '940000',
                        'dayChange' => '1.0638',
                    ],
                ],
            ]),
        ]);

        $this->artisan('market:sync-prices --limit=250')
            ->expectsOutputToContain('Successfully synchronized 2 Nobitex asset prices.')
            ->assertExitCode(0);

        $this->assertDatabaseHas('market_asset_quotes', [
            'market_asset_id' => $bitcoin->id,
            'quote_currency' => 'USDT',
            'price' => '65000.250000000000000000000000',
            'provider' => 'nobitex',
        ]);

        $this->assertDatabaseHas('market_asset_quotes', [
            'market_asset_id' => $ethereum->id,
            'quote_currency' => 'USDT',
            'price' => '3200.500000000000000000000000',
            'percent_change_24h' => '-0.50000000',
            'provider' => 'nobitex',
        ]);

        $this->assertDatabaseHas('market_exchange_rates', [
            'base_currency' => 'USDT',
            'quote_currency' => 'TOMAN',
            'rate' => '95000.000000000000',
            'provider' => 'nobitex',
            'provider_pair' => 'usdt-rls',
        ]);
    }

    public function test_assets_without_a_nobitex_market_are_skipped_and_old_quotes_removed(): void
    {
        $bitcoin = MarketAsset::query()->create([
            'provider' => 'coinmarketcap',
            'provider_asset_id' => 1,
            'name' => 'Bitcoin',
            'symbol' => 'BTC',
            'slug' => 'bitcoin',
            'market_cap_rank' => 1,
            'is_active' => true,
        ]);

        $ethereum = MarketAsset::query()->create([
            'provider' => 'coinmarketcap',
            'provider_asset_id' => 2,
            'name' => 'Ethereum',
            'symbol' => 'ETH',
            'slug' => 'ethereum',
            'market_cap_rank' => 2,
            'is_active' => true,
        ]);

        foreach ([$bitcoin, $ethereum] as $asset) {
            \Illuminate\Support\Facades\DB::table('market_asset_quotes')->insert([
                'market_asset_id' => $asset->id,
                'quote_currency' => 'USDT',
                'price' => '1',
                'provider' => 'old-provider',
                'fetched_at' => now(),
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        Http::fake([
            '*market/stats*' => Http::response([
                'status' => 'ok',
                'stats' => [
                    'btc-rls' => [
                        'latest' => '61750000000',
                        'dayOpen' => '60000000000',
                        'dayChange' => '2.9',
                        'volumeDst' => '1000000',
                    ],
                    'usdt-rls' => [
                        'latest' => '950000',
                        'dayOpen' => '940000',
                        'dayChange' => '1.06',
                    ],
                ],
            ]),
        ]);

        $this->artisan('market:sync-prices --limit=250')
            ->expectsOutputToContain('Successfully synchronized 1 Nobitex asset prices.')
            ->expectsOutputToContain('Skipped 1 assets')
            ->assertExitCode(0);

        $this->assertDatabaseCount('market_asset_quotes', 1);
        $this->assertDatabaseHas('market_asset_quotes', [
            'market_asset_id' => $bitcoin->id,
            'provider' => 'nobitex',
        ]);
        $this->assertDatabaseMissing('market_asset_quotes', [
            'market_asset_id' => $ethereum->id,
            'quote_currency' => 'USDT',
        ]);
    }

    public function test_dry_run_fetches_nobitex_prices_without_persisting_them(): void
    {
        MarketAsset::query()->create([
            'provider' => 'coinmarketcap',
            'provider_asset_id' => 1,
            'name' => 'Bitcoin',
            'symbol' => 'BTC',
            'slug' => 'bitcoin',
            'market_cap_rank' => 1,
            'is_active' => true,
        ]);

        Http::fake([
            '*market/stats*' => Http::response([
                'status' => 'ok',
                'stats' => [
                    'btc-rls' => [
                        'latest' => '61750000000',
                        'dayOpen' => '60000000000',
                        'dayChange' => '2.9',
                        'volumeDst' => '1000000',
                    ],
                    'usdt-rls' => [
                        'latest' => '950000',
                        'dayOpen' => '940000',
                        'dayChange' => '1.06',
                    ],
                ],
            ]),
        ]);

        $this->artisan('market:sync-prices --limit=250 --dry-run')
            ->expectsOutputToContain('validated (database unchanged)')
            ->assertExitCode(0);

        $this->assertDatabaseCount('market_asset_quotes', 0);
        $this->assertDatabaseCount('market_exchange_rates', 0);
    }
}

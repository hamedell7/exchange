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

    public function test_command_synchronizes_binance_spot_usdt_quotes_and_the_toman_exchange_rate(): void
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

        Http::fake([
            '*api/v3/exchangeInfo*' => Http::response([
                'symbols' => [[
                    'symbol' => 'BTCUSDT',
                    'status' => 'TRADING',
                    'baseAsset' => 'BTC',
                    'quoteAsset' => 'USDT',
                    'isSpotTradingAllowed' => true,
                ]],
            ]),
            '*api/v3/ticker/24hr*' => Http::response([[
                'symbol' => 'BTCUSDT',
                'lastPrice' => '65000.25',
                'quoteVolume' => '25000000000',
                'priceChangePercent' => '2.5',
                'closeTime' => 1795514400000,
            ]]),
            '*market/stats*' => Http::response([
                'status' => 'ok',
                'stats' => ['usdt-rls' => ['latest' => '950000']],
            ]),
        ]);

        $this->artisan('market:sync-prices --limit=250')
            ->expectsOutputToContain('Successfully synchronized 1 Binance Spot asset prices.')
            ->assertExitCode(0);

        $this->assertDatabaseHas('market_asset_quotes', [
            'market_asset_id' => $bitcoin->id,
            'quote_currency' => 'USDT',
            'price' => '65000.25',
            'volume_24h' => '25000000000',
            'percent_change_24h' => '2.5',
            'provider' => 'binance',
        ]);

        $this->assertDatabaseHas('market_exchange_rates', [
            'base_currency' => 'USDT',
            'quote_currency' => 'TOMAN',
            'rate' => '95000',
            'provider' => 'nobitex',
            'provider_pair' => 'usdt-rls',
        ]);
    }

    public function test_assets_without_a_binance_usdt_spot_market_are_skipped(): void
    {
        $assetsBySymbol = [];

        foreach ([
            ['id' => 1, 'name' => 'Bitcoin', 'symbol' => 'BTC'],
            ['id' => 2, 'name' => 'Ethereum', 'symbol' => 'ETH'],
        ] as $asset) {
            $assetsBySymbol[$asset['symbol']] = MarketAsset::query()->create([
                'provider' => 'coinmarketcap',
                'provider_asset_id' => $asset['id'],
                'name' => $asset['name'],
                'symbol' => $asset['symbol'],
                'slug' => strtolower($asset['name']),
                'market_cap_rank' => $asset['id'],
                'is_active' => true,
            ]);
        }

        Http::fake([
            '*api/v3/exchangeInfo*' => Http::response([
                'symbols' => [[
                    'symbol' => 'BTCUSDT',
                    'status' => 'TRADING',
                    'baseAsset' => 'BTC',
                    'quoteAsset' => 'USDT',
                    'isSpotTradingAllowed' => true,
                ]],
            ]),
            '*api/v3/ticker/24hr*' => Http::response([[
                'symbol' => 'BTCUSDT',
                'lastPrice' => '65000',
                'quoteVolume' => '25000000000',
                'priceChangePercent' => '2.5',
                'closeTime' => 1795514400000,
            ]]),
            '*market/stats*' => Http::response([
                'status' => 'ok',
                'stats' => ['usdt-rls' => ['latest' => '950000']],
            ]),
        ]);

        $this->artisan('market:sync-prices --limit=250')
            ->expectsOutputToContain('Successfully synchronized 1 Binance Spot asset prices.')
            ->expectsOutputToContain('Skipped 1 assets')
            ->assertExitCode(0);

        $this->assertDatabaseCount('market_asset_quotes', 1);
        $this->assertDatabaseHas('market_asset_quotes', [
            'market_asset_id' => $assetsBySymbol['BTC']->id,
            'provider' => 'binance',
        ]);
        $this->assertDatabaseMissing('market_asset_quotes', [
            'market_asset_id' => $assetsBySymbol['ETH']->id,
            'quote_currency' => 'USDT',
        ]);
    }

    public function test_dry_run_fetches_binance_prices_without_persisting_them(): void
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
            '*api/v3/exchangeInfo*' => Http::response([
                'symbols' => [[
                    'symbol' => 'BTCUSDT',
                    'status' => 'TRADING',
                    'baseAsset' => 'BTC',
                    'quoteAsset' => 'USDT',
                    'isSpotTradingAllowed' => true,
                ]],
            ]),
            '*api/v3/ticker/24hr*' => Http::response([[
                'symbol' => 'BTCUSDT',
                'lastPrice' => '65000',
                'quoteVolume' => '25000000000',
                'priceChangePercent' => '2.5',
                'closeTime' => 1795514400000,
            ]]),
            '*market/stats*' => Http::response([
                'status' => 'ok',
                'stats' => ['usdt-rls' => ['latest' => '950000']],
            ]),
        ]);

        $this->artisan('market:sync-prices --limit=250 --dry-run')
            ->expectsOutputToContain('validated (database unchanged)')
            ->assertExitCode(0);

        $this->assertDatabaseCount('market_asset_quotes', 0);
        $this->assertDatabaseCount('market_exchange_rates', 0);
    }
}

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

    public function test_command_synchronizes_usdt_quotes_and_the_toman_exchange_rate(): void
    {
        config()->set('services.coinmarketcap.api_key', 'test-api-key');

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
            '*cryptocurrency/quotes/latest*' => Http::response([
                'data' => [
                    '1' => [
                        'id' => 1,
                        'quote' => [
                            'USDT' => [
                                'price' => 65000.25,
                                'market_cap' => 1280000000000,
                                'volume_24h' => 25000000000,
                                'percent_change_1h' => 0.12,
                                'percent_change_24h' => 2.5,
                                'percent_change_7d' => -1.25,
                                'last_updated' => '2026-11-24T10:00:00.000Z',
                            ],
                        ],
                    ],
                ],
                'status' => ['error_code' => 0, 'error_message' => null],
            ]),
            '*market/stats*' => Http::response([
                'status' => 'ok',
                'stats' => [
                    'usdt-rls' => ['latest' => '950000'],
                ],
            ]),
        ]);

        $this->artisan('market:sync-prices --limit=250')
            ->expectsOutputToContain('Successfully synchronized 1 asset prices.')
            ->assertExitCode(0);

        $this->assertDatabaseHas('market_asset_quotes', [
            'quote_currency' => 'USDT',
            'price' => '65000.25',
            'percent_change_24h' => '2.5',
            'provider' => 'coinmarketcap',
        ]);

        $this->assertDatabaseHas('market_exchange_rates', [
            'base_currency' => 'USDT',
            'quote_currency' => 'TOMAN',
            'rate' => '95000',
            'provider' => 'nobitex',
            'provider_pair' => 'usdt-rls',
        ]);
    }

    public function test_dry_run_fetches_prices_without_persisting_them(): void
    {
        config()->set('services.coinmarketcap.api_key', 'test-api-key');

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
            '*cryptocurrency/quotes/latest*' => Http::response([
                'data' => [
                    '1' => [
                        'id' => 1,
                        'quote' => ['USDT' => ['price' => 65000]],
                    ],
                ],
                'status' => ['error_code' => 0],
            ]),
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

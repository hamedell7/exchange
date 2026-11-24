<?php

declare(strict_types=1);

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

final class SyncMarketAssetsCommandTest extends TestCase
{
    use RefreshDatabase;

    public function test_command_imports_asset_map_and_static_metadata(): void
    {
        config()->set('services.coinmarketcap.api_key', 'test-api-key');

        Http::fake([
            '*cryptocurrency/map*' => Http::response([
                'data' => [[
                    'id' => 1, 'rank' => 1, 'name' => 'Bitcoin',
                    'symbol' => 'BTC', 'slug' => 'bitcoin', 'is_active' => 1,
                ]],
                'status' => ['error_code' => 0, 'error_message' => null],
            ]),
            '*cryptocurrency/info*' => Http::response([
                'data' => ['1' => [
                    'id' => 1,
                    'logo' => 'https://example.test/bitcoin.png',
                    'description' => 'Bitcoin description',
                    'urls' => ['website' => ['https://bitcoin.org']],
                    'tags' => ['mineable'],
                    'platform' => null,
                    'date_added' => '2013-04-28T00:00:00.000Z',
                ]],
                'status' => ['error_code' => '0', 'error_message' => null],
            ]),
        ]);

        $this->artisan('market:sync-assets', ['--limit' => 1])
            ->expectsOutputToContain('Successfully synchronized 1 assets.')
            ->assertExitCode(0);

        $this->assertDatabaseHas('market_assets', [
            'provider' => 'coinmarketcap',
            'provider_asset_id' => 1,
            'name' => 'Bitcoin',
            'symbol' => 'BTC',
            'market_cap_rank' => 1,
            'logo_url' => 'https://example.test/bitcoin.png',
        ]);
    }

    public function test_dry_run_does_not_write_assets(): void
    {
        config()->set('services.coinmarketcap.api_key', 'test-api-key');

        Http::fake([
            '*cryptocurrency/map*' => Http::response([
                'data' => [[
                    'id' => 1, 'rank' => 1, 'name' => 'Bitcoin',
                    'symbol' => 'BTC', 'slug' => 'bitcoin', 'is_active' => 1,
                ]],
                'status' => ['error_code' => 0],
            ]),
            '*cryptocurrency/info*' => Http::response([
                'data' => ['1' => [
                    'id' => 1, 'logo' => 'https://example.test/bitcoin.png',
                    'urls' => ['website' => ['https://bitcoin.org']],
                ]],
                'status' => ['error_code' => 0],
            ]),
        ]);

        $this->artisan('market:sync-assets', ['--limit' => 1, '--dry-run' => true])
            ->expectsOutputToContain('database unchanged')
            ->assertExitCode(0);

        $this->assertDatabaseCount('market_assets', 0);
    }
}

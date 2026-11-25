<?php

declare(strict_types=1);

namespace App\Services\MarketData;

use App\Models\MarketAsset;
use Illuminate\Support\Facades\DB;
use RuntimeException;

final class MarketPriceSyncService
{
    public function __construct(
        private readonly NobitexClient $nobitex,
        private readonly NobitexMarketStatsMapper $mapper,
    ) {}

    /**
     * Synchronize the selected assets from one all-market Nobitex snapshot.
     *
     * @param array<int, MarketAsset> $assets
     * @return array{count: int, skipped_count: int, usdt_toman_rate: string}
     */
    public function sync(array $assets, bool $dryRun = false): array
    {
        if ($assets === []) {
            throw new RuntimeException('There are no active ranked assets to synchronize.');
        }

        $stats = $this->nobitex->latestMarketStats();
        $mapped = $this->mapper->map($assets, $stats);

        if ($mapped['rows'] === []) {
            throw new RuntimeException('Nobitex did not return any valid USDT prices for the selected assets.');
        }

        $now = now();
        $selectedAssetIds = array_values(array_unique(array_map(
            static fn (MarketAsset $asset): int => (int) $asset->id,
            $assets,
        )));
        $pricedAssetIds = array_column($mapped['rows'], 'market_asset_id');
        $unpricedAssetIds = array_values(array_diff($selectedAssetIds, $pricedAssetIds));
        $exchangeRate = $mapped['exchange_rate'];

        if (! $dryRun) {
            DB::transaction(function () use ($mapped, $unpricedAssetIds, $exchangeRate, $now): void {
                DB::table('market_asset_quotes')->upsert(
                    $mapped['rows'],
                    ['market_asset_id', 'quote_currency'],
                    [
                        'price', 'market_cap', 'volume_24h',
                        'percent_change_1h', 'percent_change_24h', 'percent_change_7d',
                        'provider', 'source_updated_at', 'fetched_at', 'updated_at',
                    ],
                );

                // Do not leave old quotes from another provider visible for
                // assets which have no usable Nobitex market.
                if ($unpricedAssetIds !== []) {
                    DB::table('market_asset_quotes')
                        ->where('quote_currency', 'USDT')
                        ->whereIn('market_asset_id', $unpricedAssetIds)
                        ->delete();
                }

                DB::table('market_exchange_rates')->upsert(
                    [[
                        'base_currency' => 'USDT',
                        'quote_currency' => 'TOMAN',
                        'rate' => $exchangeRate['rate'],
                        'provider' => 'nobitex',
                        'provider_pair' => $exchangeRate['pair'],
                        'fetched_at' => $now,
                        'created_at' => $now,
                        'updated_at' => $now,
                    ]],
                    ['base_currency', 'quote_currency'],
                    ['rate', 'provider', 'provider_pair', 'fetched_at', 'updated_at'],
                );
            });
        }

        return [
            'count' => count($mapped['rows']),
            'skipped_count' => $mapped['skipped_count'],
            'usdt_toman_rate' => $exchangeRate['rate'],
        ];
    }
}

<?php

declare(strict_types=1);

namespace App\Services\MarketData;

use App\Models\MarketAsset;
use Illuminate\Support\Facades\DB;
use RuntimeException;

final class MarketPriceSyncService
{
    public function __construct(
        private readonly BinanceSpotClient $binance,
        private readonly NobitexClient $nobitex,
    ) {}

    /**
     * Persist Binance Spot USDT quotes and the USDT/Toman conversion rate.
     * Duplicate symbols and assets without a Binance USDT Spot market are
     * skipped, and any old quote for those selected assets is removed.
     *
     * @param array<int, MarketAsset> $assets
     * @return array{count: int, skipped_count: int, usdt_toman_rate: string}
     */
    public function sync(array $assets, bool $dryRun = false): array
    {
        if ($assets === []) {
            throw new RuntimeException('There are no active ranked assets to synchronize.');
        }

        $assetsBySymbol = [];
        $symbolCounts = [];

        foreach ($assets as $asset) {
            $symbol = strtoupper(trim((string) $asset->symbol));

            if ($symbol === '') {
                continue;
            }

            $symbolCounts[$symbol] = ($symbolCounts[$symbol] ?? 0) + 1;
            $assetsBySymbol[$symbol] = $asset;
        }

        $uniqueSymbols = array_keys(array_filter(
            $symbolCounts,
            static fn (int $count): bool => $count === 1,
        ));

        $quotesBySymbol = $this->binance->latestUsdtQuotes($uniqueSymbols);
        $now = now();
        $rows = [];

        foreach ($uniqueSymbols as $symbol) {
            $asset = $assetsBySymbol[$symbol] ?? null;
            $quote = $quotesBySymbol[$symbol] ?? null;

            if (! $asset instanceof MarketAsset || ! is_array($quote)) {
                continue;
            }

            $price = $this->decimal($quote['price'] ?? null, 24);

            if ($price === null || bccomp($price, '0', 24) <= 0) {
                continue;
            }

            $rows[] = [
                'market_asset_id' => $asset->id,
                'quote_currency' => 'USDT',
                'price' => $price,
                'market_cap' => null,
                'volume_24h' => $this->decimal($quote['volume_24h'] ?? null, 8),
                'percent_change_1h' => null,
                'percent_change_24h' => $this->decimal($quote['percent_change_24h'] ?? null, 8),
                'percent_change_7d' => null,
                'provider' => 'binance',
                'source_updated_at' => $quote['source_updated_at'] ?? $now,
                'fetched_at' => $now,
                'created_at' => $now,
                'updated_at' => $now,
            ];
        }

        $exchangeRate = $this->nobitex->latestUsdtTomanRate();
        $selectedAssetIds = array_values(array_unique(array_map(
            static fn (MarketAsset $asset): int => (int) $asset->id,
            $assets,
        )));
        $pricedAssetIds = array_column($rows, 'market_asset_id');
        $unpricedAssetIds = array_values(array_diff($selectedAssetIds, $pricedAssetIds));

        if (! $dryRun) {
            DB::transaction(function () use ($rows, $unpricedAssetIds, $exchangeRate, $now): void {
                if ($rows !== []) {
                    DB::table('market_asset_quotes')->upsert(
                        $rows,
                        ['market_asset_id', 'quote_currency'],
                        [
                            'price', 'market_cap', 'volume_24h',
                            'percent_change_1h', 'percent_change_24h', 'percent_change_7d',
                            'provider', 'source_updated_at', 'fetched_at', 'updated_at',
                        ],
                    );
                }

                // Remove old CMC/other-provider quotes when Binance has no
                // valid USDT market for an asset in this selected batch.
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
            'count' => count($rows),
            'skipped_count' => count($assets) - count($rows),
            'usdt_toman_rate' => $exchangeRate['rate'],
        ];
    }

    private function decimal(mixed $value, int $scale): ?string
    {
        if (! is_scalar($value) || ! is_numeric($value)) {
            return null;
        }

        return bcadd(trim((string) $value), '0', $scale);
    }
}

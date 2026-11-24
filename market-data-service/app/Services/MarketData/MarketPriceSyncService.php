<?php

declare(strict_types=1);

namespace App\Services\MarketData;

use App\Models\MarketAsset;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use RuntimeException;

final class MarketPriceSyncService
{
    public function __construct(
        private readonly CoinMarketCapClient $coinMarketCap,
        private readonly NobitexClient $nobitex,
    ) {}

    /**
     * Fetch and persist available USDT quotes and the USDT/Toman conversion rate.
     *
     * @param array<int, MarketAsset> $assets
     * @return array{count: int, skipped_count: int, usdt_toman_rate: string}
     */
    public function sync(array $assets, bool $dryRun = false): array
    {
        if ($assets === []) {
            throw new RuntimeException('There are no active ranked assets to synchronize.');
        }

        $providerIds = array_map(
            static fn (MarketAsset $asset): int => (int) $asset->provider_asset_id,
            $assets,
        );

        $quotesByProviderId = [];

        foreach (array_chunk($providerIds, 250) as $batch) {
            foreach ($this->coinMarketCap->latestUsdtQuotes($batch) as $providerId => $quote) {
                $quotesByProviderId[(int) $providerId] = $quote;
            }
        }

        $now = now();
        $rows = [];
        $skippedCount = 0;

        foreach ($assets as $asset) {
            $providerId = (int) $asset->provider_asset_id;
            $providerQuote = $quotesByProviderId[$providerId]['quote']['USDT'] ?? null;
            $price = is_array($providerQuote)
                ? $this->decimal($providerQuote['price'] ?? null, 24)
                : null;

            // CMC can omit assets with no current quote. Keep syncing the rest.
            if ($price === null || (float) $price <= 0) {
                $skippedCount++;
                continue;
            }

            $sourceUpdatedAt = isset($providerQuote['last_updated'])
                ? CarbonImmutable::parse((string) $providerQuote['last_updated'])
                : null;

            $rows[] = [
                'market_asset_id' => $asset->id,
                'quote_currency' => 'USDT',
                'price' => $price,
                'market_cap' => $this->decimal($providerQuote['market_cap'] ?? null, 8),
                'volume_24h' => $this->decimal($providerQuote['volume_24h'] ?? null, 8),
                'percent_change_1h' => $this->decimal($providerQuote['percent_change_1h'] ?? null, 8),
                'percent_change_24h' => $this->decimal($providerQuote['percent_change_24h'] ?? null, 8),
                'percent_change_7d' => $this->decimal($providerQuote['percent_change_7d'] ?? null, 8),
                'provider' => 'coinmarketcap',
                'source_updated_at' => $sourceUpdatedAt,
                'fetched_at' => $now,
                'created_at' => $now,
                'updated_at' => $now,
            ];
        }

        if ($rows === []) {
            throw new RuntimeException('CoinMarketCap did not return any valid USDT prices for the requested assets.');
        }

        $exchangeRate = $this->nobitex->latestUsdtTomanRate();

        if (! $dryRun) {
            DB::transaction(function () use ($rows, $exchangeRate, $now): void {
                DB::table('market_asset_quotes')->upsert(
                    $rows,
                    ['market_asset_id', 'quote_currency'],
                    [
                        'price', 'market_cap', 'volume_24h',
                        'percent_change_1h', 'percent_change_24h', 'percent_change_7d',
                        'provider', 'source_updated_at', 'fetched_at', 'updated_at',
                    ],
                );

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
            'skipped_count' => $skippedCount,
            'usdt_toman_rate' => $exchangeRate['rate'],
        ];
    }

    private function decimal(mixed $value, int $scale): ?string
    {
        if (! is_numeric($value)) {
            return null;
        }

        $formatted = number_format((float) $value, $scale, '.', '');

        return rtrim(rtrim($formatted, '0'), '.') ?: '0';
    }
}

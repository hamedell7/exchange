<?php

declare(strict_types=1);

namespace App\Services\MarketData;

use App\Events\MarketPricesUpdated;
use App\Models\MarketAsset;
use App\Models\MarketExchangeRate;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

final class BinanceLivePriceProcessor
{
    /**
     * @param array<int, array<string, mixed>> $tickers
     * @param array<string, MarketAsset> $assetsByPair
     */
    public function process(array $tickers, array $assetsByPair): int
    {
        if ($tickers === []) {
            return 0;
        }

        $exchangeRate = MarketExchangeRate::query()
            ->where('base_currency', 'USDT')
            ->where('quote_currency', 'TOMAN')
            ->first();

        $now = now();
        $rows = [];
        $updates = [];

        foreach ($tickers as $ticker) {
            $pair = strtoupper((string) ($ticker['symbol'] ?? ''));
            $asset = $assetsByPair[$pair] ?? null;
            $price = $this->decimal($ticker['price'] ?? null, 24);

            if (! $asset instanceof MarketAsset || $price === null || bccomp($price, '0', 24) <= 0) {
                continue;
            }

            $change = $this->decimal($ticker['percent_change_24h'] ?? null, 8);
            $volume = $this->decimal($ticker['quote_volume'] ?? null, 8);
            $sourceUpdatedAt = is_numeric($ticker['event_time'] ?? null)
                ? CarbonImmutable::createFromTimestampMs((int) $ticker['event_time'])
                : CarbonImmutable::now();
            $priceToman = $exchangeRate !== null
                ? bcmul($price, (string) $exchangeRate->rate, 8)
                : null;

            $rows[] = [
                'market_asset_id' => $asset->id,
                'quote_currency' => 'USDT',
                'price' => $price,
                'market_cap' => null,
                'volume_24h' => $volume,
                'percent_change_1h' => null,
                'percent_change_24h' => $change,
                'percent_change_7d' => null,
                'provider' => 'binance',
                'source_updated_at' => $sourceUpdatedAt,
                'fetched_at' => $now,
                'created_at' => $now,
                'updated_at' => $now,
            ];

            $updates[] = [
                'asset_id' => (int) $asset->id,
                'symbol' => (string) $asset->symbol,
                'price_usdt' => $price,
                'price_toman' => $priceToman,
                'percent_change_24h' => $change,
                'price_updated_at' => $sourceUpdatedAt->toISOString(),
            ];
        }

        if ($rows === []) {
            return 0;
        }

        DB::table('market_asset_quotes')->upsert(
            $rows,
            ['market_asset_id', 'quote_currency'],
            [
                'price', 'market_cap', 'volume_24h',
                'percent_change_1h', 'percent_change_24h', 'percent_change_7d',
                'provider', 'source_updated_at', 'fetched_at', 'updated_at',
            ],
        );

        event(new MarketPricesUpdated(
            $updates,
            $exchangeRate?->rate === null ? null : (string) $exchangeRate->rate,
            $exchangeRate?->fetched_at?->toISOString(),
        ));

        return count($updates);
    }

    private function decimal(mixed $value, int $scale): ?string
    {
        if (! is_scalar($value) || ! is_numeric($value)) {
            return null;
        }

        return bcadd(trim((string) $value), '0', $scale);
    }
}

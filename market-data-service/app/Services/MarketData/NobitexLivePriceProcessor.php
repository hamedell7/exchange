<?php

declare(strict_types=1);

namespace App\Services\MarketData;

use App\Events\MarketPricesUpdated;
use App\Models\MarketAsset;
use Illuminate\Support\Facades\DB;

final class NobitexLivePriceProcessor
{
    public function __construct(
        private readonly NobitexMarketStatsMapper $mapper,
    ) {}

    /**
     * Process a public:market-stats-all publication.
     *
     * @param array<string, array<string, mixed>> $stats
     * @param array<string, MarketAsset> $assetsBySymbol
     */
    public function process(array $stats, array $assetsBySymbol): int
    {
        if ($stats === [] || $assetsBySymbol === []) {
            return 0;
        }

        $assets = array_values($assetsBySymbol);
        $mapped = $this->mapper->map($assets, $stats);

        if ($mapped['rows'] === []) {
            return 0;
        }

        $assetIds = array_column($mapped['rows'], 'market_asset_id');
        $existingQuotes = DB::table('market_asset_quotes')
            ->where('quote_currency', 'USDT')
            ->whereIn('market_asset_id', $assetIds)
            ->get(['market_asset_id', 'price', 'volume_24h', 'percent_change_24h', 'provider'])
            ->keyBy('market_asset_id');

        $changedRows = [];
        foreach ($mapped['rows'] as $row) {
            $existing = $existingQuotes->get($row['market_asset_id']);

            if (
                $existing === null
                || ! $this->sameDecimal($existing->price, $row['price'], 24)
                || ! $this->sameNullableDecimal($existing->volume_24h, $row['volume_24h'], 8)
                || ! $this->sameNullableDecimal($existing->percent_change_24h, $row['percent_change_24h'], 8)
                || $existing->provider !== 'nobitex'
            ) {
                $changedRows[] = $row;
            }
        }

        $exchangeRate = $mapped['exchange_rate'];
        $currentRate = DB::table('market_exchange_rates')
            ->where('base_currency', 'USDT')
            ->where('quote_currency', 'TOMAN')
            ->first(['rate']);
        $rateChanged = $currentRate === null || ! $this->sameDecimal($currentRate->rate, $exchangeRate['rate'], 12);

        if ($changedRows === [] && ! $rateChanged) {
            return 0;
        }

        $now = now();

        DB::transaction(function () use ($changedRows, $exchangeRate, $rateChanged, $now): void {
            if ($changedRows !== []) {
                DB::table('market_asset_quotes')->upsert(
                    $changedRows,
                    ['market_asset_id', 'quote_currency'],
                    [
                        'price', 'market_cap', 'volume_24h',
                        'percent_change_1h', 'percent_change_24h', 'percent_change_7d',
                        'provider', 'source_updated_at', 'fetched_at', 'updated_at',
                    ],
                );
            }

            if ($rateChanged) {
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
            }
        });

        // A changed USDT/Toman rate changes the Toman value for every asset,
        // even when their USDT prices have not moved.
        $updates = $rateChanged ? $mapped['updates'] : $this->updatesForRows($mapped['updates'], $changedRows);

        event(new MarketPricesUpdated(
            $updates,
            $exchangeRate['rate'],
            $now->toISOString(),
            'nobitex',
        ));

        return count($updates);
    }

    /**
     * @param array<int, array<string, mixed>> $updates
     * @param array<int, array<string, mixed>> $rows
     * @return array<int, array<string, int|string|null>>
     */
    private function updatesForRows(array $updates, array $rows): array
    {
        $changedIds = array_fill_keys(array_map(
            static fn (array $row): int => (int) $row['market_asset_id'],
            $rows,
        ), true);

        return array_values(array_filter(
            $updates,
            static fn (array $update): bool => isset($changedIds[(int) $update['asset_id']]),
        ));
    }

    private function sameDecimal(mixed $left, mixed $right, int $scale): bool
    {
        return is_numeric($left)
            && is_numeric($right)
            && bccomp((string) $left, (string) $right, $scale) === 0;
    }

    private function sameNullableDecimal(mixed $left, mixed $right, int $scale): bool
    {
        if ($left === null || $right === null) {
            return $left === null && $right === null;
        }

        return $this->sameDecimal($left, $right, $scale);
    }
}

<?php

declare(strict_types=1);

namespace App\Services\MarketData;

use App\Models\MarketAsset;
use Carbon\CarbonImmutable;
use RuntimeException;

final class NobitexMarketStatsMapper
{
    /**
     * Map Nobitex stats to the existing USDT quote model.
     *
     * Prefer a direct <asset>-USDT market. If Nobitex only has an RLS/IRT
     * market, derive the USDT value through the live USDT/RLS market rate.
     *
     * @param array<int, MarketAsset> $assets
     * @param array<string, array<string, mixed>> $rawStats
     * @return array{
     *   rows: array<int, array<string, mixed>>,
     *   updates: array<int, array<string, int|string|null>>,
     *   skipped_count: int,
     *   exchange_rate: array{rate: string, pair: string, source_updated_at: string}
     * }
     */
    public function map(array $assets, array $rawStats): array
    {
        $stats = $this->normalizeStats($rawStats);
        $exchangeRate = $this->extractUsdtTomanRate($stats);
        $usdtRial = $this->positiveDecimal(
            $stats['usdt-rls']['latest'] ?? $stats['usdt-irt']['latest'] ?? null,
            24,
        );

        if ($usdtRial === null) {
            throw new RuntimeException('Nobitex market statistics do not contain a usable USDT/RLS market.');
        }

        $symbolCounts = [];
        foreach ($assets as $asset) {
            $symbol = strtolower(trim((string) $asset->symbol));
            if ($symbol !== '') {
                $symbolCounts[$symbol] = ($symbolCounts[$symbol] ?? 0) + 1;
            }
        }

        $now = now();
        $rows = [];
        $updates = [];
        $skippedCount = 0;

        foreach ($assets as $asset) {
            $symbol = strtolower(trim((string) $asset->symbol));

            // An ambiguous provider symbol must never be mapped by guesswork.
            if ($symbol === '' || ($symbolCounts[$symbol] ?? 0) !== 1) {
                $skippedCount++;
                continue;
            }

            $directPair = $symbol.'-usdt';
            $rialPair = $symbol.'-rls';
            $irtPair = $symbol.'-irt';

            $market = $stats[$directPair] ?? null;
            $quoteCurrency = 'USDT';
            $providerPair = $directPair;

            if (! is_array($market) || $this->positiveDecimal($market['latest'] ?? null, 24) === null) {
                $market = $stats[$rialPair] ?? $stats[$irtPair] ?? null;
                $quoteCurrency = 'RLS';
                $providerPair = isset($stats[$rialPair]) ? $rialPair : $irtPair;
            }

            if (! is_array($market)) {
                $skippedCount++;
                continue;
            }

            $latest = $this->positiveDecimal($market['latest'] ?? null, 24);

            if ($latest === null) {
                $skippedCount++;
                continue;
            }

            $priceUsdt = $quoteCurrency === 'USDT'
                ? $latest
                : bcdiv($latest, $usdtRial, 24);

            if (bccomp($priceUsdt, '0', 24) <= 0) {
                $skippedCount++;
                continue;
            }

            $priceToman = bcmul($priceUsdt, $exchangeRate['rate'], 8);
            $change = $quoteCurrency === 'USDT'
                ? $this->decimal($market['dayChange'] ?? null, 8)
                : $this->crossRateDayChange($market, $stats['usdt-rls'] ?? $stats['usdt-irt'] ?? []);
            $volume = $this->positiveOrZeroDecimal($market['volumeDst'] ?? null, 8);

            if ($quoteCurrency === 'RLS' && $volume !== null) {
                $volume = bcdiv($volume, $usdtRial, 8);
            }

            $sourceUpdatedAt = CarbonImmutable::now();

            $rows[] = [
                'market_asset_id' => $asset->id,
                'quote_currency' => 'USDT',
                'price' => $priceUsdt,
                'market_cap' => null,
                'volume_24h' => $volume,
                'percent_change_1h' => null,
                'percent_change_24h' => $change,
                'percent_change_7d' => null,
                'provider' => 'nobitex',
                'source_updated_at' => $sourceUpdatedAt,
                'fetched_at' => $now,
                'created_at' => $now,
                'updated_at' => $now,
            ];

            $updates[] = [
                'asset_id' => (int) $asset->id,
                'symbol' => (string) $asset->symbol,
                'price_usdt' => $priceUsdt,
                'price_toman' => $priceToman,
                'percent_change_24h' => $change,
                'price_updated_at' => $sourceUpdatedAt->toISOString(),
                'provider_pair' => $providerPair,
            ];
        }

        return [
            'rows' => $rows,
            'updates' => $updates,
            'skipped_count' => $skippedCount,
            'exchange_rate' => $exchangeRate,
        ];
    }

    /**
     * @param array<string, array<string, mixed>> $rawStats
     * @return array<string, array<string, mixed>>
     */
    private function normalizeStats(array $rawStats): array
    {
        $stats = [];

        foreach ($rawStats as $pair => $market) {
            if (! is_array($market) || ! str_contains((string) $pair, '-')) {
                continue;
            }

            $normalizedPair = strtolower(trim((string) $pair));
            $normalizedPair = preg_replace('/-(?:irt|rial)$/', '-rls', $normalizedPair) ?? $normalizedPair;
            $stats[$normalizedPair] = $market;
        }

        return $stats;
    }

    /**
     * @param array<string, array<string, mixed>> $stats
     * @return array{rate: string, pair: string, source_updated_at: string}
     */
    private function extractUsdtTomanRate(array $stats): array
    {
        $market = $stats['usdt-rls'] ?? null;
        $latest = is_array($market) ? $this->positiveDecimal($market['latest'] ?? null, 24) : null;

        if ($latest === null) {
            throw new RuntimeException('Nobitex did not return a valid USDT/RLS market price.');
        }

        return [
            'rate' => bcdiv($latest, '10', 12),
            'pair' => 'usdt-rls',
            'source_updated_at' => now()->toISOString(),
        ];
    }

    /**
     * Compute change in the asset's USDT price when only its RLS market exists.
     *
     * @param array<string, mixed> $assetMarket
     * @param array<string, mixed> $usdtMarket
     */
    private function crossRateDayChange(array $assetMarket, array $usdtMarket): ?string
    {
        $assetOpen = $this->positiveDecimal($assetMarket['dayOpen'] ?? null, 24);
        $usdtOpen = $this->positiveDecimal($usdtMarket['dayOpen'] ?? null, 24);

        if ($assetOpen !== null && $usdtOpen !== null) {
            $assetOpenUsdt = bcdiv($assetOpen, $usdtOpen, 24);
            $assetNow = $this->positiveDecimal($assetMarket['latest'] ?? null, 24);
            $usdtNow = $this->positiveDecimal($usdtMarket['latest'] ?? null, 24);

            if ($assetNow !== null && $usdtNow !== null) {
                $assetNowUsdt = bcdiv($assetNow, $usdtNow, 24);
                return bcmul(bcsub(bcdiv($assetNowUsdt, $assetOpenUsdt, 16), '1', 16), '100', 8);
            }
        }

        $assetChange = $this->decimal($assetMarket['dayChange'] ?? null, 8);
        $usdtChange = $this->decimal($usdtMarket['dayChange'] ?? null, 8);

        if ($assetChange === null || $usdtChange === null) {
            return null;
        }

        $assetFactor = bcadd('1', bcdiv($assetChange, '100', 16), 16);
        $usdtFactor = bcadd('1', bcdiv($usdtChange, '100', 16), 16);

        if (bccomp($usdtFactor, '0', 16) <= 0) {
            return null;
        }

        return bcmul(bcsub(bcdiv($assetFactor, $usdtFactor, 16), '1', 16), '100', 8);
    }

    private function positiveDecimal(mixed $value, int $scale): ?string
    {
        $decimal = $this->decimal($value, $scale);

        return $decimal !== null && bccomp($decimal, '0', $scale) > 0 ? $decimal : null;
    }

    private function positiveOrZeroDecimal(mixed $value, int $scale): ?string
    {
        $decimal = $this->decimal($value, $scale);

        return $decimal !== null && bccomp($decimal, '0', $scale) >= 0 ? $decimal : null;
    }

    private function decimal(mixed $value, int $scale): ?string
    {
        if (! is_scalar($value) || ! is_numeric($value)) {
            return null;
        }

        return bcadd(trim((string) $value), '0', $scale);
    }
}

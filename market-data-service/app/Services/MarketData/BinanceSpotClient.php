<?php

declare(strict_types=1);

namespace App\Services\MarketData;

use Carbon\CarbonImmutable;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;
use RuntimeException;

final class BinanceSpotClient
{
    /**
     * Resolve only active, unambiguous Binance Spot USDT markets.
     *
     * @param array<int, string> $baseSymbols
     * @return array<string, string>
     */
    public function spotUsdtMarkets(array $baseSymbols): array
    {
        $wanted = array_values(array_unique(array_filter(
            array_map(static fn (string $symbol): string => strtoupper(trim($symbol)), $baseSymbols),
            static fn (string $symbol): bool => $symbol !== '',
        )));

        if ($wanted === []) {
            return [];
        }

        $payload = $this->request()->get('/api/v3/exchangeInfo')->throw()->json();

        if (! is_array($payload) || ! is_array($payload['symbols'] ?? null)) {
            throw new RuntimeException('Binance returned an invalid exchange-info response.');
        }

        $wantedLookup = array_fill_keys($wanted, true);
        $candidates = [];

        foreach ($payload['symbols'] as $market) {
            if (! is_array($market)) {
                continue;
            }

            $base = strtoupper((string) ($market['baseAsset'] ?? ''));
            $quote = strtoupper((string) ($market['quoteAsset'] ?? ''));
            $symbol = strtoupper((string) ($market['symbol'] ?? ''));

            if (
                $base === ''
                || $symbol === ''
                || ! isset($wantedLookup[$base])
                || $quote !== 'USDT'
                || ($market['status'] ?? null) !== 'TRADING'
                || (($market['isSpotTradingAllowed'] ?? true) !== true)
            ) {
                continue;
            }

            $candidates[$base][] = $symbol;
        }

        $markets = [];

        foreach ($candidates as $base => $symbols) {
            // Do not guess where the provider mapping is ambiguous.
            if (count($symbols) === 1) {
                $markets[$base] = $symbols[0];
            }
        }

        return $markets;
    }

    /**
     * @param array<int, string> $baseSymbols
     * @return array<string, array{price: string, volume_24h: ?string, percent_change_24h: ?string, source_updated_at: CarbonImmutable}>
     */
    public function latestUsdtQuotes(array $baseSymbols): array
    {
        $markets = $this->spotUsdtMarkets($baseSymbols);

        if ($markets === []) {
            return [];
        }

        $baseByPair = array_flip($markets);
        $quotes = [];

        foreach (array_chunk(array_values($markets), 100) as $batch) {
            $payload = $this->request()->get('/api/v3/ticker/24hr', [
                'symbols' => json_encode($batch, JSON_THROW_ON_ERROR),
            ])->throw()->json();

            if (! is_array($payload)) {
                throw new RuntimeException('Binance returned an invalid 24-hour ticker response.');
            }

            foreach ($payload as $ticker) {
                if (! is_array($ticker)) {
                    continue;
                }

                $pair = strtoupper((string) ($ticker['symbol'] ?? ''));
                $base = $baseByPair[$pair] ?? null;
                $price = $ticker['lastPrice'] ?? null;

                if ($base === null || ! is_numeric($price) || bccomp((string) $price, '0', 24) <= 0) {
                    continue;
                }

                $quotes[$base] = [
                    'price' => (string) $price,
                    'volume_24h' => is_numeric($ticker['quoteVolume'] ?? null)
                        ? (string) $ticker['quoteVolume']
                        : null,
                    'percent_change_24h' => is_numeric($ticker['priceChangePercent'] ?? null)
                        ? (string) $ticker['priceChangePercent']
                        : null,
                    'source_updated_at' => is_numeric($ticker['closeTime'] ?? null)
                        ? CarbonImmutable::createFromTimestampMs((int) $ticker['closeTime'])
                        : CarbonImmutable::now(),
                ];
            }
        }

        return $quotes;
    }

    private function request(): PendingRequest
    {
        return Http::baseUrl(rtrim((string) config('services.binance.spot_base_url'), '/'))
            ->acceptJson()
            ->connectTimeout(5)
            ->timeout((int) config('services.binance.timeout', 20))
            ->retry(2, 500);
    }
}

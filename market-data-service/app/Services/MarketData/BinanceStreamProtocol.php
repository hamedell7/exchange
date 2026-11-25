<?php

declare(strict_types=1);

namespace App\Services\MarketData;

use JsonException;
use RuntimeException;

final class BinanceStreamProtocol
{
    /**
     * Decode Binance's all-market rolling 24-hour ticker payload.
     *
     * @return array<int, array{symbol: string, price: string, percent_change_24h: ?string, quote_volume: ?string, event_time: ?int}>
     * @throws JsonException
     */
    public static function decodeTickers(string $payload): array
    {
        $decoded = json_decode($payload, true, 512, JSON_THROW_ON_ERROR);

        if (! is_array($decoded)) {
            throw new RuntimeException('Binance sent a non-object WebSocket payload.');
        }

        if (isset($decoded['data']) && is_array($decoded['data'])) {
            $decoded = $decoded['data'];
        }

        $items = array_is_list($decoded) ? $decoded : [$decoded];
        $tickers = [];

        foreach ($items as $item) {
            if (! is_array($item)) {
                continue;
            }

            $symbol = strtoupper(trim((string) ($item['s'] ?? '')));
            $price = $item['c'] ?? null;

            if ($symbol === '' || ! is_numeric($price) || bccomp((string) $price, '0', 24) <= 0) {
                continue;
            }

            $tickers[] = [
                'symbol' => $symbol,
                'price' => (string) $price,
                'percent_change_24h' => is_numeric($item['P'] ?? null) ? (string) $item['P'] : null,
                'quote_volume' => is_numeric($item['q'] ?? null) ? (string) $item['q'] : null,
                'event_time' => is_numeric($item['E'] ?? null) ? (int) $item['E'] : null,
            ];
        }

        return $tickers;
    }

    public static function isServerShutdownPayload(string $payload): bool
    {
        $decoded = json_decode($payload, true);

        if (! is_array($decoded)) {
            return false;
        }

        if (isset($decoded['data']) && is_array($decoded['data'])) {
            $decoded = $decoded['data'];
        }

        return ($decoded['e'] ?? null) === 'serverShutdown';
    }
}

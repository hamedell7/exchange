<?php

declare(strict_types=1);

namespace App\Services\MarketData;

use JsonException;
use RuntimeException;

final class CoinMarketCapStreamProtocol
{
    public const PRICE_CHANNEL = 'market@crypto_latest_price';

    /**
     * @param array<int, int> $cryptoIds
     * @return array<string, mixed>
     */
    public static function subscriptionMessage(array $cryptoIds, int $requestId = 1): array
    {
        $ids = array_values(array_unique(array_filter(
            array_map('intval', $cryptoIds),
            static fn (int $id): bool => $id > 0,
        )));

        if ($ids === []) {
            throw new RuntimeException('At least one valid CoinMarketCap crypto ID is required.');
        }

        return [
            'id' => $requestId,
            'method' => 'subscribe',
            'channel' => self::PRICE_CHANNEL,
            'params' => ['crypto_ids' => $ids],
        ];
    }

    /**
     * @return array<string, int|string>
     */
    public static function pingMessage(int $requestId): array
    {
        return [
            'id' => $requestId,
            'method' => 'ping',
        ];
    }

    /**
     * @return array<string, mixed>
     * @throws JsonException
     */
    public static function decode(string $payload): array
    {
        $message = json_decode($payload, true, 512, JSON_THROW_ON_ERROR);

        if (! is_array($message)) {
            throw new RuntimeException('CoinMarketCap sent a non-object WebSocket message.');
        }

        return $message;
    }
}

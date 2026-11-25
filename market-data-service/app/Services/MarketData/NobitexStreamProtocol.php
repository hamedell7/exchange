<?php

declare(strict_types=1);

namespace App\Services\MarketData;

use JsonException;

final class NobitexStreamProtocol
{
    public const MARKET_STATS_CHANNEL = 'public:market-stats-all';

    /** @return array<string, mixed> */
    public static function connectMessage(): array
    {
        return [
            'id' => 1,
            'connect' => (object) [],
        ];
    }

    /** @return array<string, mixed> */
    public static function subscribeMessage(int $requestId = 2): array
    {
        return [
            'id' => $requestId,
            'subscribe' => [
                'channel' => self::MARKET_STATS_CHANNEL,
            ],
        ];
    }

    /**
     * Return public all-market statistics from a Centrifugo push.
     *
     * @return array<string, array<string, mixed>>|null
     * @throws JsonException
     */
    public static function decodeStatsPublication(string $payload): ?array
    {
        $message = json_decode($payload, true, 512, JSON_THROW_ON_ERROR);

        if (! is_array($message)) {
            return null;
        }

        $push = $message['push'] ?? null;

        if (! is_array($push) || ($push['channel'] ?? null) !== self::MARKET_STATS_CHANNEL) {
            return null;
        }

        $data = $push['pub']['data'] ?? null;

        if (is_string($data)) {
            $data = json_decode($data, true, 512, JSON_THROW_ON_ERROR);
        }

        if (! is_array($data)) {
            return null;
        }

        // Some gateway versions wrap the actual map in a "stats" property.
        if (is_array($data['stats'] ?? null)) {
            $data = $data['stats'];
        }

        return $data;
    }

    /** @throws JsonException */
    public static function isApplicationPing(string $payload): bool
    {
        $message = json_decode($payload, true, 512, JSON_THROW_ON_ERROR);

        return is_array($message) && $message === [];
    }
}

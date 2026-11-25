<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Services\MarketData\NobitexStreamProtocol;
use PHPUnit\Framework\TestCase;

final class NobitexStreamProtocolTest extends TestCase
{
    public function test_it_builds_an_unauthenticated_centrifugo_connect_message(): void
    {
        self::assertEquals(
            ['id' => 1, 'connect' => new \stdClass()],
            NobitexStreamProtocol::connectMessage(),
        );
    }

    public function test_it_builds_a_subscription_for_all_public_market_stats(): void
    {
        self::assertSame([
            'id' => 2,
            'subscribe' => ['channel' => 'public:market-stats-all'],
        ], NobitexStreamProtocol::subscribeMessage());
    }

    public function test_it_decodes_market_stats_from_a_centrifugo_publication(): void
    {
        $stats = [
            'btc-irt' => ['latest' => '61750000000', 'dayChange' => '2.9'],
            'usdt-irt' => ['latest' => '950000', 'dayChange' => '1.06'],
        ];

        $payload = json_encode([
            'push' => [
                'channel' => 'public:market-stats-all',
                'pub' => [
                    'data' => json_encode($stats, JSON_THROW_ON_ERROR),
                ],
            ],
        ], JSON_THROW_ON_ERROR);

        self::assertSame($stats, NobitexStreamProtocol::decodeStatsPublication($payload));
    }

    public function test_it_ignores_publications_from_other_channels(): void
    {
        $payload = json_encode([
            'push' => [
                'channel' => 'public:orderbook-BTCIRT',
                'pub' => ['data' => '{}'],
            ],
        ], JSON_THROW_ON_ERROR);

        self::assertNull(NobitexStreamProtocol::decodeStatsPublication($payload));
    }

    public function test_it_recognizes_centrifugo_application_ping(): void
    {
        self::assertTrue(NobitexStreamProtocol::isApplicationPing('{}'));
        self::assertFalse(NobitexStreamProtocol::isApplicationPing('{"id":1}'));
    }
}

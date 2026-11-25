<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Services\MarketData\BinanceStreamProtocol;
use PHPUnit\Framework\TestCase;

final class BinanceStreamProtocolTest extends TestCase
{
    public function test_it_decodes_the_all_market_24_hour_ticker_array(): void
    {
        $tickers = BinanceStreamProtocol::decodeTickers(json_encode([
            [
                'e' => '1dTicker',
                'E' => 1778663880111,
                's' => 'BTCUSDT',
                'c' => '65000.125',
                'P' => '1.25',
                'q' => '123456789.10',
            ],
        ], JSON_THROW_ON_ERROR));

        self::assertSame([
            [
                'symbol' => 'BTCUSDT',
                'price' => '65000.125',
                'percent_change_24h' => '1.25',
                'quote_volume' => '123456789.10',
                'event_time' => 1778663880111,
            ],
        ], $tickers);
    }

    public function test_it_decodes_a_combined_stream_envelope(): void
    {
        $tickers = BinanceStreamProtocol::decodeTickers(json_encode([
            'stream' => '!ticker_1d@arr',
            'data' => [[
                's' => 'ETHUSDT',
                'c' => '3200.50',
                'P' => '-0.5',
                'q' => '500000',
                'E' => 1778663880111,
            ]],
        ], JSON_THROW_ON_ERROR));

        self::assertSame('ETHUSDT', $tickers[0]['symbol']);
        self::assertSame('-0.5', $tickers[0]['percent_change_24h']);
    }

    public function test_it_ignores_invalid_or_zero_price_tickers(): void
    {
        $tickers = BinanceStreamProtocol::decodeTickers(json_encode([
            ['s' => 'BTCUSDT', 'c' => '0'],
            ['s' => '', 'c' => '1'],
            ['s' => 'ETHUSDT', 'c' => '3200'],
        ], JSON_THROW_ON_ERROR));

        self::assertCount(1, $tickers);
        self::assertSame('ETHUSDT', $tickers[0]['symbol']);
    }

    public function test_it_identifies_a_planned_server_shutdown(): void
    {
        self::assertTrue(BinanceStreamProtocol::isServerShutdownPayload(json_encode([
            'e' => 'serverShutdown',
            'E' => 1778663880111,
            'T' => 1778664000000,
        ], JSON_THROW_ON_ERROR)));
    }
}

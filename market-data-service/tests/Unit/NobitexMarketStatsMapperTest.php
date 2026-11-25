<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Models\MarketAsset;
use App\Services\MarketData\NobitexMarketStatsMapper;
use PHPUnit\Framework\TestCase;

final class NobitexMarketStatsMapperTest extends TestCase
{
    public function test_it_converts_riel_markets_to_usdt_and_toman(): void
    {
        $bitcoin = new MarketAsset();
        $bitcoin->setAttribute('id', 10);
        $bitcoin->setAttribute('name', 'Bitcoin');
        $bitcoin->setAttribute('symbol', 'BTC');

        $mapped = (new NobitexMarketStatsMapper())->map([$bitcoin], [
            'btc-irt' => [
                'latest' => '61750000000',
                'dayOpen' => '60000000000',
                'dayChange' => '2.9',
                'volumeDst' => '1000000000',
            ],
            'usdt-irt' => [
                'latest' => '950000',
                'dayOpen' => '940000',
                'dayChange' => '1.06',
            ],
        ]);

        self::assertCount(1, $mapped['rows']);
        self::assertSame('65000.000000000000000000000000', $mapped['rows'][0]['price']);
        self::assertSame('95000.000000000000', $mapped['exchange_rate']['rate']);
        self::assertSame('nobitex', $mapped['rows'][0]['provider']);
        self::assertSame(0, $mapped['skipped_count']);
    }

    public function test_it_prefers_a_direct_usdt_market_when_available(): void
    {
        $ethereum = new MarketAsset();
        $ethereum->setAttribute('id', 11);
        $ethereum->setAttribute('name', 'Ethereum');
        $ethereum->setAttribute('symbol', 'ETH');

        $mapped = (new NobitexMarketStatsMapper())->map([$ethereum], [
            'eth-usdt' => [
                'latest' => '3200.50',
                'dayChange' => '-0.5',
                'volumeDst' => '150000',
            ],
            'usdt-rls' => ['latest' => '950000'],
        ]);

        self::assertCount(1, $mapped['rows']);
        self::assertSame('3200.500000000000000000000000', $mapped['rows'][0]['price']);
        self::assertSame('-0.50000000', $mapped['rows'][0]['percent_change_24h']);
    }

    public function test_it_skips_duplicate_symbols_and_missing_markets(): void
    {
        $first = new MarketAsset();
        $first->setAttribute('id', 1);
        $first->setAttribute('symbol', 'BTC');

        $second = new MarketAsset();
        $second->setAttribute('id', 2);
        $second->setAttribute('symbol', 'BTC');

        $missing = new MarketAsset();
        $missing->setAttribute('id', 3);
        $missing->setAttribute('symbol', 'ETH');

        $mapped = (new NobitexMarketStatsMapper())->map([$first, $second, $missing], [
            'btc-rls' => ['latest' => '1000000000'],
            'usdt-rls' => ['latest' => '950000'],
        ]);

        self::assertCount(0, $mapped['rows']);
        self::assertSame(3, $mapped['skipped_count']);
    }
}

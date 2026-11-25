<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Services\MarketData\CoinMarketCapStreamProtocol;
use GuzzleHttp\Psr7\Response;
use PHPUnit\Framework\TestCase;
use RuntimeException;

final class CoinMarketCapStreamProtocolTest extends TestCase
{
    public function test_it_builds_a_subscription_for_numeric_provider_ids(): void
    {
        self::assertSame([
            'id' => 7,
            'method' => 'subscribe',
            'channel' => 'market@crypto_latest_price',
            'params' => ['crypto_ids' => [1, 1027]],
        ], CoinMarketCapStreamProtocol::subscriptionMessage([1, 1027, 1, 0, -4], 7));
    }

    public function test_it_rejects_an_empty_subscription(): void
    {
        $this->expectException(RuntimeException::class);

        CoinMarketCapStreamProtocol::subscriptionMessage([0, -1]);
    }

    public function test_it_decodes_a_price_push_without_changing_provider_fields(): void
    {
        $message = CoinMarketCapStreamProtocol::decode(json_encode([
            'type' => 'data',
            'channel' => CoinMarketCapStreamProtocol::PRICE_CHANNEL,
            'data' => ['cid' => 1, 'p' => 65000.25, 'p24h' => 1.5],
            'ts' => 1778663880111,
        ], JSON_THROW_ON_ERROR));

        self::assertSame('data', $message['type']);
        self::assertSame(1, $message['data']['cid']);
        self::assertSame(65000.25, $message['data']['p']);
        self::assertSame(1778663880111, $message['ts']);
    }


    public function test_it_accepts_a_valid_websocket_upgrade_handshake(): void
    {
        $key = base64_encode(random_bytes(16));
        $accept = base64_encode(sha1(
            $key.'258EAFA5-E914-47DA-95CA-C5AB0DC85B11',
            true,
        ));
        $response = new Response(101, [
            'Upgrade' => 'websocket',
            'Connection' => 'keep-alive, Upgrade',
            'Sec-WebSocket-Accept' => $accept,
        ]);

        self::assertTrue(CoinMarketCapStreamProtocol::isValidHandshakeResponse($response, $key));
    }

    public function test_it_rejects_an_invalid_websocket_upgrade_handshake(): void
    {
        $response = new Response(200, [
            'Upgrade' => 'websocket',
            'Connection' => 'Upgrade',
            'Sec-WebSocket-Accept' => 'invalid',
        ]);

        self::assertFalse(CoinMarketCapStreamProtocol::isValidHandshakeResponse($response, 'test-key'));
    }

    public function test_it_builds_a_protocol_ping(): void
    {
        self::assertSame([
            'id' => 9,
            'method' => 'ping',
        ], CoinMarketCapStreamProtocol::pingMessage(9));
    }
}

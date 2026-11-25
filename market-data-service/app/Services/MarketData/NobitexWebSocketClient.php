<?php

declare(strict_types=1);

namespace App\Services\MarketData;

use GuzzleHttp\Psr7\HttpFactory;
use GuzzleHttp\Psr7\Message as Psr7Message;
use GuzzleHttp\Psr7\Uri;
use Ratchet\RFC6455\Messaging\CloseFrameChecker;
use Ratchet\RFC6455\Messaging\Frame;
use Ratchet\RFC6455\Messaging\FrameInterface;
use Ratchet\RFC6455\Messaging\MessageBuffer;
use Ratchet\RFC6455\Messaging\MessageInterface;
use React\EventLoop\Loop;
use React\EventLoop\LoopInterface;
use React\EventLoop\TimerInterface;
use React\Socket\ConnectionInterface;
use React\Socket\Connector;
use React\Socket\ConnectorInterface;
use RuntimeException;
use Throwable;

/**
 * One long-lived, unauthenticated connection to Nobitex public market stats.
 * Uses the Centrifugo JSON protocol documented by Nobitex.
 */
final class NobitexWebSocketClient
{
    private string $endpoint = '';
    private const MAX_HANDSHAKE_BYTES = 32768;
    private const MAX_FRAME_BYTES = 4194304;
    private const MAX_RECONNECT_DELAY_SECONDS = 60;

    private LoopInterface $loop;
    private ConnectorInterface $connector;
    private ?ConnectionInterface $stream = null;
    private ?MessageBuffer $messageBuffer = null;
    private ?TimerInterface $handshakeTimer = null;
    private ?TimerInterface $reconnectTimer = null;
    private int $reconnectAttempts = 0;
    private bool $handshakeComplete = false;
    private bool $centrifugoConnected = false;
    private bool $subscriptionRequested = false;
    private bool $reconnectScheduled = false;
    private bool $stopped = false;
    private bool $fatalError = false;

    /** @var callable(array<string, array<string, mixed>>): void */
    private $onStats;

    /** @var callable(string, string): void */
    private $onStatus;

    /**
     * @param callable(array<string, array<string, mixed>>): void $onStats
     * @param callable(string, string): void $onStatus
     */
    public function listen(callable $onStats, callable $onStatus): bool
    {
        $this->onStats = $onStats;
        $this->onStatus = $onStatus;
        $this->endpoint = (string) config(
            'services.nobitex.websocket_url',
            'wss://ws.nobitex.ir/connection/websocket',
        );
        $this->loop = Loop::get();
        $this->connector = new Connector(['timeout' => 15], $this->loop);

        $this->registerSignalHandlers();
        $this->connect();
        $this->loop->run();

        return ! $this->fatalError;
    }

    private function connect(): void
    {
        if ($this->stopped) {
            return;
        }

        $parts = parse_url($this->endpoint);

        if (! is_array($parts) || ($parts['scheme'] ?? null) !== 'wss' || empty($parts['host'])) {
            $this->notifyStatus('error', 'NOBITEX_WS_URL must be a valid wss:// URL.');
            $this->stop(true);
            return;
        }

        $host = (string) $parts['host'];
        $port = (int) ($parts['port'] ?? 443);
        $this->notifyStatus('info', "Connecting to Nobitex {$host}:{$port}...");

        $this->connector->connect("tls://{$host}:{$port}")->then(
            function (ConnectionInterface $stream) use ($parts, $host, $port): void {
                try {
                    $this->beginHandshake($stream, $parts, $host, $port);
                } catch (Throwable $exception) {
                    $stream->close();
                    $this->scheduleReconnect($exception);
                }
            },
            fn (Throwable $exception) => $this->scheduleReconnect($exception),
        );
    }

    /** @param array<string, mixed> $parts */
    private function beginHandshake(ConnectionInterface $stream, array $parts, string $host, int $port): void
    {
        $this->stream = $stream;
        $this->handshakeComplete = false;
        $this->centrifugoConnected = false;
        $this->subscriptionRequested = false;
        $this->messageBuffer = null;

        $path = (string) ($parts['path'] ?? '/connection/websocket');
        $query = isset($parts['query']) ? '?'.$parts['query'] : '';
        $authority = $host.($port === 443 ? '' : ':'.$port);
        $uri = new Uri('https://'.$authority.$path.$query);
        $webSocketKey = base64_encode(random_bytes(16));

        $request = (new HttpFactory())->createRequest('GET', $uri)
            ->withHeader('Host', $authority)
            ->withHeader('User-Agent', 'Exchange-MarketData/1.0')
            ->withHeader('Connection', 'Upgrade')
            ->withHeader('Upgrade', 'websocket')
            ->withHeader('Sec-WebSocket-Version', '13')
            ->withHeader('Sec-WebSocket-Key', $webSocketKey);

        $handshakeBuffer = '';

        $this->handshakeTimer = $this->loop->addTimer(15, function () use ($stream): void {
            if (! $this->handshakeComplete) {
                $this->notifyStatus('warning', 'Nobitex WebSocket handshake timed out.');
                $stream->close();
            }
        });

        $stream->on('data', function (string $chunk) use (&$handshakeBuffer, $stream, $webSocketKey): void {
            if ($this->handshakeComplete) {
                $this->feedFrames($chunk);
                return;
            }

            $handshakeBuffer .= $chunk;

            if (strlen($handshakeBuffer) > self::MAX_HANDSHAKE_BYTES) {
                $this->notifyStatus('error', 'Nobitex handshake response exceeded the allowed size.');
                $this->stop(true);
                return;
            }

            $headerEnd = strpos($handshakeBuffer, "\r\n\r\n");

            if ($headerEnd === false) {
                return;
            }

            $rawHeaders = substr($handshakeBuffer, 0, $headerEnd + 4);
            $remaining = substr($handshakeBuffer, $headerEnd + 4);

            try {
                $response = Psr7Message::parseResponse($rawHeaders);
            } catch (Throwable $exception) {
                $this->notifyStatus('error', 'Could not parse Nobitex handshake response: '.$exception->getMessage());
                $this->stop(true);
                return;
            }

            if (! $this->isValidHandshake($response, $webSocketKey)) {
                $statusCode = $response->getStatusCode();

                if ($statusCode === 429 || $statusCode >= 500) {
                    $this->scheduleReconnect(new RuntimeException("Nobitex WebSocket handshake returned HTTP {$statusCode}."));
                    return;
                }

                $this->notifyStatus('error', "Nobitex rejected the WebSocket handshake with HTTP {$statusCode}.");
                $this->stop(true);
                return;
            }

            $this->handshakeComplete = true;
            $this->cancelTimer($this->handshakeTimer);
            $this->handshakeTimer = null;
            $this->onConnected($stream);

            if ($remaining !== '') {
                $this->feedFrames($remaining);
            }
        });

        $stream->on('error', function (Throwable $exception): void {
            $this->notifyStatus('warning', 'Nobitex socket error: '.$exception->getMessage());
        });

        $stream->on('close', function () use ($stream): void {
            if ($this->stream === $stream) {
                $this->stream = null;
                $this->messageBuffer = null;
            }

            $this->cancelTimer($this->handshakeTimer);
            $this->handshakeTimer = null;

            if (! $this->stopped) {
                $this->scheduleReconnect(new RuntimeException('Nobitex WebSocket connection closed.'));
            }
        });

        $stream->write(Psr7Message::toString($request));
    }

    private function isValidHandshake(\Psr\Http\Message\ResponseInterface $response, string $key): bool
    {
        if ($response->getStatusCode() !== 101) {
            return false;
        }

        $connectionTokens = array_map(
            static fn (string $token): string => strtolower(trim($token)),
            explode(',', $response->getHeaderLine('Connection')),
        );
        $expectedAccept = base64_encode(sha1($key.'258EAFA5-E914-47DA-95CA-C5AB0DC85B11', true));

        return strtolower(trim($response->getHeaderLine('Upgrade'))) === 'websocket'
            && in_array('upgrade', $connectionTokens, true)
            && hash_equals($expectedAccept, trim($response->getHeaderLine('Sec-WebSocket-Accept')));
    }

    private function onConnected(ConnectionInterface $stream): void
    {
        $this->notifyStatus('connected', 'WebSocket transport connected; starting Nobitex Centrifugo session.');

        $buffer = null;
        $buffer = new MessageBuffer(
            new CloseFrameChecker(),
            function (MessageInterface $message) use ($stream): void {
                $this->handleMessage($message->getPayload(), $stream);
            },
            function (FrameInterface $frame) use ($stream, &$buffer): void {
                $opcode = $frame->getOpcode();

                if ($opcode === Frame::OP_PING) {
                    $buffer?->sendFrame($buffer->newFrame($frame->getPayload(), true, Frame::OP_PONG));
                    return;
                }

                if ($opcode === Frame::OP_CLOSE) {
                    $payload = $frame->getPayload();
                    $closeCode = strlen($payload) >= 2 ? unpack('n', substr($payload, 0, 2))[1] : null;
                    $this->notifyStatus(
                        'warning',
                        'Nobitex closed the WebSocket connection'.($closeCode === null ? '.' : " ({$closeCode})."),
                    );
                    $buffer?->sendFrame($buffer->newFrame($payload, true, Frame::OP_CLOSE));
                    $stream->end();
                }
            },
            expectMask: false,
            maxMessagePayloadSize: self::MAX_FRAME_BYTES,
            maxFramePayloadSize: self::MAX_FRAME_BYTES,
            sender: static function (string $frame) use ($stream): void {
                if ($stream->isWritable()) {
                    $stream->write($frame);
                }
            },
        );

        $this->messageBuffer = $buffer;
        $this->sendJson(NobitexStreamProtocol::connectMessage());
    }

    private function feedFrames(string $chunk): void
    {
        try {
            $this->messageBuffer?->onData($chunk);
        } catch (Throwable $exception) {
            $this->notifyStatus('warning', 'Invalid Nobitex WebSocket frame: '.$exception->getMessage());

            if ($this->stream !== null) {
                $this->stream->close();
            }
        }
    }

    private function handleMessage(string $payload, ConnectionInterface $stream): void
    {
        try {
            if (NobitexStreamProtocol::isApplicationPing($payload)) {
                $this->sendText('{}');
                return;
            }

            $decoded = json_decode($payload, true, 512, JSON_THROW_ON_ERROR);

            if (! is_array($decoded)) {
                return;
            }

            if (isset($decoded['id']) && (int) $decoded['id'] === 1 && isset($decoded['connect'])) {
                if (isset($decoded['error'])) {
                    $this->notifyStatus('error', 'Nobitex Centrifugo connect failed: '.json_encode($decoded['error']));
                    $this->stop(true);
                    return;
                }

                $this->centrifugoConnected = true;
                $this->reconnectAttempts = 0;
                $this->notifyStatus('connected', 'Connected to Nobitex public market-data service.');
                $this->sendJson(NobitexStreamProtocol::subscribeMessage());
                $this->subscriptionRequested = true;
                return;
            }

            if (isset($decoded['id']) && (int) $decoded['id'] === 2 && isset($decoded['subscribe'])) {
                if (isset($decoded['error'])) {
                    $this->notifyStatus('error', 'Nobitex market-stats subscription failed: '.json_encode($decoded['error']));
                    $this->stop(true);
                    return;
                }

                $this->notifyStatus('connected', 'Subscribed to public:market-stats-all.');
                return;
            }

            $stats = NobitexStreamProtocol::decodeStatsPublication($payload);

            if ($stats !== null && $stats !== []) {
                ($this->onStats)($stats);
            }
        } catch (Throwable $exception) {
            $this->notifyStatus('warning', 'Ignored an invalid Nobitex WebSocket message: '.$exception->getMessage());
        }
    }

    /** @param array<string, mixed> $message */
    private function sendJson(array $message): void
    {
        $this->sendText(json_encode($message, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES));
    }

    private function sendText(string $payload): void
    {
        if ($this->messageBuffer === null || $this->stream === null || ! $this->stream->isWritable()) {
            return;
        }

        $frame = $this->messageBuffer->newFrame($payload, true, Frame::OP_TEXT);
        $this->messageBuffer->sendFrame($frame);
    }

    private function scheduleReconnect(Throwable $exception): void
    {
        if ($this->stopped || $this->reconnectScheduled) {
            return;
        }

        $this->reconnectScheduled = true;
        $this->cancelTimer($this->handshakeTimer);
        $this->handshakeTimer = null;
        $this->messageBuffer = null;

        $current = $this->stream;
        $this->stream = null;

        if ($current !== null) {
            $current->close();
        }

        $delay = min(2 ** min($this->reconnectAttempts, 6), self::MAX_RECONNECT_DELAY_SECONDS);
        $this->reconnectAttempts++;

        $this->notifyStatus('warning', $exception->getMessage());
        $this->notifyStatus('info', "Reconnecting to Nobitex in {$delay} second(s)...");

        $this->reconnectTimer = $this->loop->addTimer((float) $delay, function (): void {
            $this->reconnectScheduled = false;
            $this->reconnectTimer = null;
            $this->connect();
        });
    }

    private function registerSignalHandlers(): void
    {
        if (! function_exists('pcntl_signal')) {
            return;
        }

        if (defined('SIGINT')) {
            $this->loop->addSignal(SIGINT, function (): void {
                $this->notifyStatus('info', 'Stopping Nobitex market stream...');
                $this->stop();
            });
        }

        if (defined('SIGTERM')) {
            $this->loop->addSignal(SIGTERM, fn () => $this->stop());
        }
    }

    private function stop(bool $fatal = false): void
    {
        $this->stopped = true;
        $this->fatalError = $this->fatalError || $fatal;
        $this->cancelTimer($this->handshakeTimer);
        $this->cancelTimer($this->reconnectTimer);
        $this->handshakeTimer = null;
        $this->reconnectTimer = null;

        if ($this->stream !== null) {
            $this->stream->close();
            $this->stream = null;
        }

        $this->messageBuffer = null;
        Loop::stop();
    }

    private function cancelTimer(?TimerInterface $timer): void
    {
        if ($timer !== null) {
            $this->loop->cancelTimer($timer);
        }
    }

    private function notifyStatus(string $level, string $message): void
    {
        if (isset($this->onStatus)) {
            ($this->onStatus)($level, $message);
        }
    }
}

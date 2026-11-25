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
 * Long-running CMC WebSocket consumer.
 *
 * The project already includes ReactPHP and Ratchet's RFC6455 implementation
 * through Laravel Reverb, so this client reuses those protocol components
 * instead of introducing a second event-loop/dependency stack.
 */
final class CoinMarketCapWebSocketClient
{
    private const DEFAULT_PING_INTERVAL_MS = 10_000;
    private const MAX_HANDSHAKE_BYTES = 32_768;
    private const MAX_FRAME_BYTES = 1_048_576;
    private const MAX_RECONNECT_DELAY_SECONDS = 60;

    private LoopInterface $loop;

    private ConnectorInterface $connector;

    private ?ConnectionInterface $stream = null;

    private ?MessageBuffer $messageBuffer = null;

    private ?TimerInterface $handshakeTimer = null;

    private ?TimerInterface $pingTimer = null;

    private ?TimerInterface $reconnectTimer = null;

    /** @var array<int, int> */
    private array $cryptoIds = [];

    private string $apiKey = '';

    private string $endpoint = '';

    private int $requestId = 1;

    private int $reconnectAttempts = 0;

    private bool $handshakeComplete = false;

    private bool $reconnectScheduled = false;

    private bool $stopped = false;

    private bool $fatalError = false;

    /** @var callable(array<string, mixed>): void */
    private $onMessage;

    /** @var callable(string, string): void */
    private $onStatus;

    /**
     * Runs until Ctrl+C, a fatal provider error, or an explicit stop.
     *
     * @param array<int, int> $cryptoIds
     * @param callable(array<string, mixed>): void $onMessage
     * @param callable(string, string): void $onStatus
     */
    public function listen(array $cryptoIds, callable $onMessage, callable $onStatus): bool
    {
        $apiKey = trim((string) config('services.coinmarketcap.api_key'));

        if ($apiKey === '') {
            throw new RuntimeException('CMC_API_KEY is missing. Set it in market-data-service/.env.');
        }

        $subscription = CoinMarketCapStreamProtocol::subscriptionMessage($cryptoIds);
        $this->cryptoIds = $subscription['params']['crypto_ids'];
        $this->apiKey = $apiKey;
        $this->endpoint = (string) config(
            'services.coinmarketcap.websocket_url',
            'wss://pro-stream.coinmarketcap.com/v1',
        );
        $this->onMessage = $onMessage;
        $this->onStatus = $onStatus;
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
            $this->notifyStatus('error', 'CMC_WS_URL must be a valid wss:// URL.');
            $this->stop(true);

            return;
        }

        $host = (string) $parts['host'];
        $port = (int) ($parts['port'] ?? 443);
        $socketUri = "tls://{$host}:{$port}";

        $this->notifyStatus('info', "Connecting to {$host}:{$port}...");

        $this->connector->connect($socketUri)->then(
            function (ConnectionInterface $stream) use ($parts, $host, $port): void {
                try {
                    $this->beginHandshake($stream, $parts, $host, $port);
                } catch (Throwable $exception) {
                    $stream->close();
                    $this->scheduleReconnect($exception);
                }
            },
            function (Throwable $exception): void {
                $this->scheduleReconnect($exception);
            },
        );
    }

    /**
     * @param array<string, mixed> $parts
     */
    private function beginHandshake(ConnectionInterface $stream, array $parts, string $host, int $port): void
    {
        $this->stream = $stream;
        $this->handshakeComplete = false;
        $this->messageBuffer = null;

        $path = (string) ($parts['path'] ?? '/');
        $query = isset($parts['query']) ? '?'.$parts['query'] : '';
        $authority = $host.($port === 443 ? '' : ':'.$port);
        $uri = new Uri('https://'.$authority.$path.$query);

        // Keep all header values strings. The current Ratchet negotiator
        // passes integer 13 to Guzzle PSR-7, which rejects non-string values.
        $webSocketKey = base64_encode(random_bytes(16));
        $request = (new HttpFactory())->createRequest('GET', $uri)
            ->withHeader('Host', $authority)
            ->withHeader('User-Agent', 'Exchange-MarketData/1.0')
            ->withHeader('Connection', 'Upgrade')
            ->withHeader('Upgrade', 'websocket')
            ->withHeader('Sec-WebSocket-Version', '13')
            ->withHeader('Sec-WebSocket-Key', $webSocketKey)
            ->withHeader('X-CMC_PRO_API_KEY', $this->apiKey);

        $handshakeBuffer = '';

        $this->handshakeTimer = $this->loop->addTimer(15, function () use ($stream): void {
            if (! $this->handshakeComplete) {
                $this->notifyStatus('warning', 'WebSocket handshake timed out.');
                $stream->close();
            }
        });

        $stream->on('data', function (string $chunk) use (
            &$handshakeBuffer,
            $stream,
            $request,
            $webSocketKey,
        ): void {
            if ($this->handshakeComplete) {
                $this->feedWebSocketFrames($chunk);

                return;
            }

            $handshakeBuffer .= $chunk;

            if (strlen($handshakeBuffer) > self::MAX_HANDSHAKE_BYTES) {
                $this->notifyStatus('error', 'CMC handshake response exceeded the allowed size.');
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

                if (! CoinMarketCapStreamProtocol::isValidHandshakeResponse($response, $webSocketKey)) {
                    throw new RuntimeException(
                        'CMC rejected the WebSocket handshake with HTTP '.$response->getStatusCode().'. Check the API key and WebSocket plan.',
                    );
                }
            } catch (Throwable $exception) {
                $this->notifyStatus('error', $exception->getMessage());
                $this->stop(true);

                return;
            }

            $this->handshakeComplete = true;
            $this->cancelTimer($this->handshakeTimer);
            $this->handshakeTimer = null;
            $this->onConnected($stream);

            if ($remaining !== '') {
                $this->feedWebSocketFrames($remaining);
            }
        });

        $stream->on('error', function (Throwable $exception): void {
            $this->notifyStatus('warning', 'Socket error: '.$exception->getMessage());
        });

        $stream->on('close', function () use ($stream): void {
            if ($this->stream === $stream) {
                $this->stream = null;
                $this->messageBuffer = null;
            }

            $this->cancelTimer($this->handshakeTimer);
            $this->handshakeTimer = null;
            $this->cancelTimer($this->pingTimer);
            $this->pingTimer = null;

            if (! $this->stopped) {
                $this->scheduleReconnect(new RuntimeException('CMC WebSocket connection closed.'));
            }
        });

        $stream->write(Psr7Message::toString($request));
    }

    private function onConnected(ConnectionInterface $stream): void
    {
        $this->reconnectScheduled = false;
        $this->notifyStatus('connected', 'WebSocket connection established. Subscribing to market prices...');

        $messageBuffer = null;
        $messageBuffer = new MessageBuffer(
            new CloseFrameChecker(),
            function (MessageInterface $message) use ($stream): void {
                $this->handleServerMessage($message->getPayload(), $stream);
            },
            function (FrameInterface $frame) use ($stream, &$messageBuffer): void {
                $opcode = $frame->getOpcode();

                if ($opcode === Frame::OP_PING) {
                    $messageBuffer?->sendFrame(
                        $messageBuffer->newFrame($frame->getPayload(), true, Frame::OP_PONG),
                    );

                    return;
                }

                if ($opcode === Frame::OP_CLOSE) {
                    $payload = $frame->getPayload();
                    $closeCode = strlen($payload) >= 2 ? unpack('n', substr($payload, 0, 2))[1] : null;
                    $reason = strlen($payload) > 2 ? substr($payload, 2) : '';

                    $this->notifyStatus(
                        'warning',
                        'CMC closed the WebSocket connection'.($closeCode === null ? '' : " ({$closeCode})").($reason === '' ? '.' : ': '.$reason),
                    );

                    if ($closeCode !== null && in_array($closeCode, [4004, 4100, 4101, 4102], true)) {
                        $this->stop(true);

                        return;
                    }

                    $messageBuffer?->sendFrame(
                        $messageBuffer->newFrame($payload, true, Frame::OP_CLOSE),
                    );
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

        $this->messageBuffer = $messageBuffer;
        $this->schedulePing((int) config(
            'services.coinmarketcap.websocket_ping_interval_ms',
            self::DEFAULT_PING_INTERVAL_MS,
        ));

        try {
            $this->sendProtocolMessage(CoinMarketCapStreamProtocol::subscriptionMessage($this->cryptoIds, $this->nextRequestId()));
        } catch (Throwable $exception) {
            $this->scheduleReconnect($exception);
        }
    }

    private function feedWebSocketFrames(string $chunk): void
    {
        try {
            $this->messageBuffer?->onData($chunk);
        } catch (Throwable $exception) {
            $this->notifyStatus('warning', 'Invalid WebSocket frame: '.$exception->getMessage());

            if ($this->stream !== null) {
                $this->stream->close();
            }
        }
    }

    private function handleServerMessage(string $payload, ConnectionInterface $stream): void
    {
        try {
            $message = CoinMarketCapStreamProtocol::decode($payload);
        } catch (Throwable $exception) {
            $this->notifyStatus('warning', 'Ignored an invalid CMC message: '.$exception->getMessage());

            return;
        }

        $type = (string) ($message['type'] ?? '');

        if ($type === 'welcome') {
            $interval = (int) ($message['ping_interval_ms'] ?? self::DEFAULT_PING_INTERVAL_MS);

            if ($interval > 0) {
                $this->schedulePing($interval);
            }

            $this->notifyStatus('info', 'CMC welcome message received.');

            return;
        }

        if ($type === 'ack') {
            if ((int) ($message['code'] ?? 0) !== 0) {
                $this->notifyStatus('error', 'CMC subscription was rejected: '.(string) ($message['msg'] ?? 'unknown error'));
                $this->stop(true);

                return;
            }

            $this->reconnectAttempts = 0;
            $this->notifyStatus(
                'subscribed',
                'Subscription accepted. Active subscriptions: '.(string) ($message['sub_count'] ?? 'unknown'),
            );

            return;
        }

        if ($type === 'pong') {
            return;
        }

        if ($type === 'error') {
            $error = CoinMarketCapStreamProtocol::describeError($message);
            $this->notifyStatus('error', "CMC error {$error['code']}: {$error['message']}");

            // A rejected request will not become valid by reconnecting.
            // Retry only provider-side internal errors; stop for all other
            // errors to avoid a tight reconnect loop and unnecessary credits.
            if ($error['code'] === '5001') {
                $stream->close();

                return;
            }

            $this->stop(true);

            return;
        }

        if ($type === 'data' && ($message['channel'] ?? null) === CoinMarketCapStreamProtocol::PRICE_CHANNEL) {
            $data = $message['data'] ?? null;

            if (is_array($data) && isset($data['cid']) && is_numeric($data['cid']) && isset($data['p']) && is_numeric($data['p'])) {
                try {
                    ($this->onMessage)($message);
                } catch (Throwable $exception) {
                    $this->notifyStatus('warning', 'Price callback failed: '.$exception->getMessage());
                }
            }
        }
    }

    /** @param array<string, mixed> $message */
    private function sendProtocolMessage(array $message): void
    {
        if ($this->messageBuffer === null || $this->stream === null || ! $this->stream->isWritable()) {
            return;
        }

        $this->messageBuffer->sendMessage(json_encode($message, JSON_THROW_ON_ERROR));
    }

    private function schedulePing(int $intervalMilliseconds): void
    {
        $this->cancelTimer($this->pingTimer);
        $seconds = max(1, $intervalMilliseconds / 1000);

        $this->pingTimer = $this->loop->addPeriodicTimer($seconds, function (): void {
            try {
                $this->sendProtocolMessage(CoinMarketCapStreamProtocol::pingMessage($this->nextRequestId()));
            } catch (Throwable $exception) {
                $this->notifyStatus('warning', 'Could not send CMC keep-alive ping: '.$exception->getMessage());

                if ($this->stream !== null) {
                    $this->stream->close();
                }
            }
        });
    }

    private function scheduleReconnect(Throwable $exception): void
    {
        if ($this->stopped || $this->reconnectScheduled) {
            return;
        }

        $this->reconnectScheduled = true;
        $this->cancelTimer($this->handshakeTimer);
        $this->handshakeTimer = null;
        $this->cancelTimer($this->pingTimer);
        $this->pingTimer = null;
        $this->messageBuffer = null;

        $currentStream = $this->stream;
        $this->stream = null;

        if ($currentStream !== null) {
            $currentStream->close();
        }

        $delay = min(2 ** min($this->reconnectAttempts, 6), self::MAX_RECONNECT_DELAY_SECONDS);
        $this->reconnectAttempts++;

        $this->notifyStatus('warning', $exception->getMessage());
        $this->notifyStatus('info', "Reconnecting in {$delay} second(s)...");

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
                $this->notifyStatus('info', 'Stopping CMC stream...');
                $this->stop();
            });
        }

        if (defined('SIGTERM')) {
            $this->loop->addSignal(SIGTERM, function (): void {
                $this->stop();
            });
        }
    }

    private function stop(bool $fatal = false): void
    {
        $this->stopped = true;
        $this->fatalError = $this->fatalError || $fatal;

        $this->cancelTimer($this->handshakeTimer);
        $this->cancelTimer($this->pingTimer);
        $this->cancelTimer($this->reconnectTimer);
        $this->handshakeTimer = null;
        $this->pingTimer = null;
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

    private function nextRequestId(): int
    {
        return $this->requestId++;
    }

    private function notifyStatus(string $level, string $message): void
    {
        if (isset($this->onStatus)) {
            ($this->onStatus)($level, $message);
        }
    }
}

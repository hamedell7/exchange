<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\MarketAsset;
use App\Services\MarketData\BinanceLivePriceProcessor;
use App\Services\MarketData\BinanceSpotClient;
use App\Services\MarketData\BinanceWebSocketClient;
use App\Services\MarketData\MarketPriceSyncService;
use Illuminate\Console\Command;
use Throwable;

final class StreamBinanceMarketData extends Command
{
    protected $signature = 'market:stream-binance
                            {--limit=250 : Maximum number of active ranked assets to stream (1-5000)}';

    protected $description = 'Stream free Binance Spot prices and broadcast live market updates.';

    public function handle(
        BinanceSpotClient $binance,
        BinanceWebSocketClient $client,
        BinanceLivePriceProcessor $processor,
        MarketPriceSyncService $syncService,
    ): int {
        $limit = filter_var($this->option('limit'), FILTER_VALIDATE_INT);

        if ($limit === false || $limit < 1 || $limit > 5000) {
            $this->components->error('The --limit option must be between 1 and 5000.');
            return self::INVALID;
        }

        $assets = MarketAsset::query()
            ->where('provider', 'coinmarketcap')
            ->where('is_active', true)
            ->whereNotNull('market_cap_rank')
            ->orderBy('market_cap_rank')
            ->limit($limit)
            ->get(['id', 'symbol']);

        if ($assets->isEmpty()) {
            $this->components->error('No active ranked assets were found. Run market:sync-assets first.');
            return self::FAILURE;
        }

        $groups = $assets->groupBy(static fn (MarketAsset $asset): string => strtoupper(trim((string) $asset->symbol)));
        $uniqueSymbols = $groups
            ->filter(static fn ($group): bool => $group->count() === 1)
            ->keys()
            ->all();

        try {
            $snapshot = $syncService->sync($assets->all());

            $this->components->info(
                "Initial Binance snapshot saved for {$snapshot['count']} asset(s). 1 USDT = {$snapshot['usdt_toman_rate']} Toman.",
            );

            $markets = $binance->spotUsdtMarkets($uniqueSymbols);
            $assetsByPair = [];

            foreach ($markets as $baseSymbol => $pair) {
                $asset = $groups->get($baseSymbol)?->first();

                if ($asset instanceof MarketAsset) {
                    $assetsByPair[$pair] = $asset;
                }
            }

            if ($assetsByPair === []) {
                $this->components->error('No selected assets have an unambiguous active Binance USDT Spot market.');
                return self::FAILURE;
            }

            $this->components->info('Starting Binance live stream for '.count($assetsByPair).' USDT Spot market(s).');
            $this->line('Live prices are saved to PostgreSQL and broadcast to Reverb channel market.prices.');
            $this->line('No Binance API key is required. Press Ctrl+C to stop.');

            $completed = $client->listen(
                static function (array $tickers) use ($processor, $assetsByPair): void {
                    $processor->process($tickers, $assetsByPair);
                },
                function (string $level, string $message): void {
                    $method = $level === 'error' ? 'error' : ($level === 'warning' ? 'warn' : 'line');
                    $this->{$method}('['.strtoupper($level).'] '.$message);
                },
            );

            return $completed ? self::SUCCESS : self::FAILURE;
        } catch (Throwable $exception) {
            report($exception);
            $this->components->error($exception->getMessage());
            return self::FAILURE;
        }
    }
}

<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\MarketAsset;
use App\Services\MarketData\MarketPriceSyncService;
use App\Services\MarketData\NobitexLivePriceProcessor;
use App\Services\MarketData\NobitexWebSocketClient;
use Illuminate\Console\Command;
use Throwable;

final class StreamNobitexMarketData extends Command
{
    protected $signature = 'market:stream-nobitex
                            {--limit=250 : Maximum number of active ranked assets to publish (1-5000)}';

    protected $description = 'Stream public Nobitex market stats and broadcast live asset prices.';

    public function handle(
        NobitexWebSocketClient $client,
        NobitexLivePriceProcessor $processor,
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

        $groups = $assets->groupBy(
            static fn (MarketAsset $asset): string => strtoupper(trim((string) $asset->symbol)),
        );
        $assetsBySymbol = [];

        foreach ($groups as $symbol => $group) {
            if ($symbol !== '' && $group->count() === 1) {
                $assetsBySymbol[$symbol] = $group->first();
            }
        }

        if ($assetsBySymbol === []) {
            $this->components->error('No unique asset symbols are available for Nobitex market mapping.');
            return self::FAILURE;
        }

        try {
            $snapshot = $syncService->sync($assets->all());

            $this->components->info(
                "Initial Nobitex snapshot saved for {$snapshot['count']} asset(s). 1 USDT = {$snapshot['usdt_toman_rate']} Toman.",
            );

            $this->components->info('Connecting to Nobitex public all-market WebSocket stream.');
            $this->line('The consumer subscribes once to public:market-stats-all.');
            $this->line('Live prices are saved to PostgreSQL and broadcast to Reverb channel market.prices.');
            $this->line('No Nobitex API token or API key is required. Press Ctrl+C to stop.');

            $completed = $client->listen(
                static function (array $stats) use ($processor, $assetsBySymbol): void {
                    $processor->process($stats, $assetsBySymbol);
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

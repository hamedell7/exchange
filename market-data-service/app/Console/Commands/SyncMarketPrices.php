<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\MarketAsset;
use App\Services\MarketData\MarketPriceSyncService;
use Illuminate\Console\Command;
use Throwable;

final class SyncMarketPrices extends Command
{
    protected $signature = 'market:sync-prices
                            {--limit=250 : Maximum number of ranked active assets to update (1-5000)}
                            {--dry-run : Fetch and validate prices without writing to the database}';

    protected $description = 'Synchronize Binance Spot USDT prices and the USDT/Toman market rate.';

    public function handle(MarketPriceSyncService $syncService): int
    {
        $limit = filter_var($this->option('limit'), FILTER_VALIDATE_INT);

        if ($limit === false || $limit < 1 || $limit > 5000) {
            $this->components->error('The --limit option must be between 1 and 5000.');
            return self::INVALID;
        }

        try {
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

            $result = $syncService->sync($assets->all(), (bool) $this->option('dry-run'));
            $mode = $this->option('dry-run') ? 'validated (database unchanged)' : 'synchronized';

            $this->components->info(
                "Successfully {$mode} {$result['count']} Binance Spot asset prices. 1 USDT = {$result['usdt_toman_rate']} Toman.",
            );

            if ($result['skipped_count'] > 0) {
                $this->components->warn(
                    "Skipped {$result['skipped_count']} assets because Binance has no unambiguous active USDT Spot market for them.",
                );
            }

            return self::SUCCESS;
        } catch (Throwable $exception) {
            report($exception);
            $this->components->error($exception->getMessage());
            return self::FAILURE;
        }
    }
}

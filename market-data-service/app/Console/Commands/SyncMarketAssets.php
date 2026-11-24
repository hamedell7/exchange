<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Services\MarketData\AssetCatalogSyncService;
use App\Services\MarketData\CoinMarketCapClient;
use Illuminate\Console\Command;
use Throwable;

final class SyncMarketAssets extends Command
{
    protected $signature = 'market:sync-assets
                            {--limit=250 : Number of top ranked active assets to import (1-5000)}
                            {--start=1 : 1-based offset for the provider ranking}
                            {--dry-run : Fetch and validate provider data without writing to the database}';

    protected $description = 'Import the ranked active asset catalog and static metadata from CoinMarketCap.';

    public function handle(
        CoinMarketCapClient $client,
        AssetCatalogSyncService $syncService,
    ): int {
        $limit = filter_var($this->option('limit'), FILTER_VALIDATE_INT);
        $start = filter_var($this->option('start'), FILTER_VALIDATE_INT);

        if ($limit === false || $limit < 1 || $limit > 5000) {
            $this->components->error('The --limit option must be between 1 and 5000.');

            return self::INVALID;
        }

        if ($start === false || $start < 1) {
            $this->components->error('The --start option must be a positive integer.');

            return self::INVALID;
        }

        try {
            $this->components->info("Fetching {$limit} ranked active assets from CoinMarketCap...");
            $assets = $client->activeAssetMap($start, $limit);

            if ($assets === []) {
                $this->components->error('CoinMarketCap returned no assets.');

                return self::FAILURE;
            }

            $providerIds = array_values(array_map(
                static fn (array $asset): int => (int) ($asset['id'] ?? 0),
                $assets,
            ));

            $metadata = $client->metadata($providerIds);

            if ($this->option('dry-run')) {
                $this->components->info(
                    'Dry run successful. '.count($assets).' assets and their metadata were fetched; database unchanged.',
                );

                return self::SUCCESS;
            }

            $synced = $syncService->sync(
                $assets,
                $metadata,
                replaceCurrentRanking: $start === 1,
            );

            $this->components->info("Successfully synchronized {$synced} assets.");

            return self::SUCCESS;
        } catch (Throwable $exception) {
            report($exception);
            $this->components->error($exception->getMessage());

            return self::FAILURE;
        }
    }
}

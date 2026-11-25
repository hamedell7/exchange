<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\MarketAsset;
use App\Services\MarketData\CoinMarketCapWebSocketClient;
use Illuminate\Console\Command;
use Throwable;

final class StreamCoinMarketCap extends Command
{
    protected $signature = 'market:stream-cmc
                            {--limit=5 : Number of top ranked assets to stream (1-100)}';

    protected $description = 'Connect to CoinMarketCap WebSocket and print live USD prices.';

    public function handle(CoinMarketCapWebSocketClient $client): int
    {
        $limit = filter_var($this->option('limit'), FILTER_VALIDATE_INT);

        if ($limit === false || $limit < 1 || $limit > 100) {
            $this->components->error('The --limit option must be between 1 and 100.');

            return self::INVALID;
        }

        if (trim((string) config('services.coinmarketcap.api_key')) === '') {
            $this->components->error('CMC_API_KEY is missing. Set it in market-data-service/.env.');

            return self::FAILURE;
        }

        $assets = MarketAsset::query()
            ->where('provider', 'coinmarketcap')
            ->where('is_active', true)
            ->whereNotNull('provider_asset_id')
            ->whereNotNull('market_cap_rank')
            ->orderBy('market_cap_rank')
            ->limit($limit)
            ->get(['provider_asset_id', 'name', 'symbol']);

        if ($assets->isEmpty()) {
            $this->components->error('No CoinMarketCap assets found. Run market:sync-assets first.');

            return self::FAILURE;
        }

        $assetsByProviderId = $assets->keyBy(static fn (MarketAsset $asset): int => (int) $asset->provider_asset_id);
        $cryptoIds = $assets->map(static fn (MarketAsset $asset): int => (int) $asset->provider_asset_id)->all();

        $this->components->info('Starting live CMC price stream for '.$assets->count().' asset(s).');
        $this->line('Prices from this CMC channel are quoted in USD, not USDT. No database values will be changed.');
        $this->line('Press Ctrl+C to stop.');

        try {
            $completed = $client->listen(
                $cryptoIds,
                function (array $envelope) use ($assetsByProviderId): void {
                    $data = $envelope['data'] ?? [];
                    $providerId = (int) ($data['cid'] ?? 0);
                    $asset = $assetsByProviderId->get($providerId);

                    if (! $asset instanceof MarketAsset) {
                        return;
                    }

                    $price = (float) $data['p'];
                    $priceLabel = rtrim(rtrim(number_format($price, 10, '.', ','), '0'), '.');
                    $change = $data['p24h'] ?? null;
                    $changeLabel = is_numeric($change)
                        ? sprintf(' | 24h: %+.3f%%', (float) $change)
                        : '';
                    $timestamp = isset($envelope['ts'])
                        ? date('H:i:s', (int) floor(((int) $envelope['ts']) / 1000))
                        : now()->format('H:i:s');

                    $this->line(sprintf(
                        '[%s] %-8s $%s%s',
                        $timestamp,
                        strtoupper((string) $asset->symbol),
                        $priceLabel,
                        $changeLabel,
                    ));
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

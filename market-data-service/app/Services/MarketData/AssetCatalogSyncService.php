<?php

declare(strict_types=1);

namespace App\Services\MarketData;

use App\Models\MarketAsset;
use Carbon\CarbonImmutable;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use RuntimeException;

final class AssetCatalogSyncService
{
    /**
     * @param array<int, array<string, mixed>> $assets
     * @param array<string, array<string, mixed>> $metadataById
     */
    public function sync(array $assets, array $metadataById, bool $replaceCurrentRanking): int
    {
        if ($assets === []) {
            throw new RuntimeException('CoinMarketCap returned an empty asset list.');
        }

        $providerIds = array_map(
            static fn (array $asset): int => (int) ($asset['id'] ?? 0),
            $assets,
        );

        if (in_array(0, $providerIds, true)) {
            throw new RuntimeException('CoinMarketCap asset map contains an invalid provider ID.');
        }

        $metadataKeys = array_map('intval', array_keys($metadataById));
        $missingMetadata = array_diff($providerIds, $metadataKeys);

        if ($missingMetadata !== []) {
            throw new RuntimeException(
                'Metadata is missing for CoinMarketCap IDs: '.implode(',', array_slice($missingMetadata, 0, 10)),
            );
        }

        $now = now();
        $rows = [];

        foreach ($assets as $asset) {
            $providerId = (int) $asset['id'];
            $metadata = $metadataById[(string) $providerId] ?? $metadataById[$providerId] ?? [];
            $website = Arr::first($metadata['urls']['website'] ?? []);
            $dateAdded = isset($metadata['date_added'])
                ? CarbonImmutable::parse($metadata['date_added'])
                : null;

            $rows[] = [
                'provider' => 'coinmarketcap',
                'provider_asset_id' => $providerId,
                'name' => (string) $asset['name'],
                'symbol' => strtoupper((string) $asset['symbol']),
                'slug' => (string) $asset['slug'],
                'logo_url' => $metadata['logo'] ?? null,
                'description' => $metadata['description'] ?? null,
                'website_url' => is_string($website) ? $website : null,
                'tags' => json_encode($metadata['tags'] ?? [], JSON_THROW_ON_ERROR),
                'platform' => isset($metadata['platform'])
                    ? json_encode($metadata['platform'], JSON_THROW_ON_ERROR)
                    : null,
                'market_cap_rank' => isset($asset['rank']) ? (int) $asset['rank'] : null,
                'is_active' => (bool) ($asset['is_active'] ?? true),
                'date_added' => $dateAdded,
                'metadata_synced_at' => $now,
                'created_at' => $now,
                'updated_at' => $now,
            ];
        }

        return DB::transaction(function () use ($rows, $replaceCurrentRanking, $now): int {
            if ($replaceCurrentRanking) {
                MarketAsset::query()
                    ->where('provider', 'coinmarketcap')
                    ->update(['market_cap_rank' => null, 'updated_at' => $now]);
            }

            MarketAsset::upsert(
                $rows,
                ['provider', 'provider_asset_id'],
                [
                    'name', 'symbol', 'slug', 'logo_url', 'description',
                    'website_url', 'tags', 'platform', 'market_cap_rank',
                    'is_active', 'date_added', 'metadata_synced_at', 'updated_at',
                ],
            );

            return count($rows);
        });
    }
}

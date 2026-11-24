<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Market;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Market\MarketAssetIndexRequest;
use App\Http\Resources\Api\V1\Market\MarketAssetResource;
use App\Models\MarketAsset;
use App\Models\MarketExchangeRate;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

final class MarketAssetController extends Controller
{
    public function index(MarketAssetIndexRequest $request): AnonymousResourceCollection
    {
        $validated = $request->validated();

        $query = MarketAsset::query()
            ->with('quote')
            ->where('provider', 'coinmarketcap')
            ->where('is_active', true)
            ->whereNotNull('market_cap_rank')
            ->orderBy('market_cap_rank');

        if (filled($validated['search'] ?? null)) {
            $search = mb_strtolower(trim($validated['search']));

            $query->where(function ($query) use ($search): void {
                $query->whereRaw('LOWER(name) LIKE ?', ['%'.$search.'%'])
                    ->orWhereRaw('LOWER(symbol) LIKE ?', ['%'.$search.'%']);
            });
        }

        $exchangeRate = MarketExchangeRate::query()
            ->where('base_currency', 'USDT')
            ->where('quote_currency', 'TOMAN')
            ->first();

        $assets = $query->paginate($validated['per_page'] ?? 50)->withQueryString();

        if ($exchangeRate !== null) {
            $assets->getCollection()->each(static function (MarketAsset $asset) use ($exchangeRate): void {
                if ($asset->quote?->price !== null) {
                    $asset->setAttribute(
                        'price_toman',
                        bcmul((string) $asset->quote->price, (string) $exchangeRate->rate, 8),
                    );
                }
            });
        }

        return MarketAssetResource::collection($assets)->additional([
            'market_data' => [
                'usdt_toman_rate' => $exchangeRate?->rate,
                'rate_updated_at' => $exchangeRate?->fetched_at?->toISOString(),
            ],
        ]);
    }
}

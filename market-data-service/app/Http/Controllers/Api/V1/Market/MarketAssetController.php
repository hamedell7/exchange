<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Market;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Market\MarketAssetIndexRequest;
use App\Http\Resources\Api\V1\Market\MarketAssetResource;
use App\Models\MarketAsset;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

final class MarketAssetController extends Controller
{
    public function index(MarketAssetIndexRequest $request): AnonymousResourceCollection
    {
        $validated = $request->validated();

        $query = MarketAsset::query()
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

        return MarketAssetResource::collection(
            $query->paginate($validated['per_page'] ?? 50)->withQueryString(),
        );
    }
}

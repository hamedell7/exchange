<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class MarketAssetQuote extends Model
{
    protected $fillable = [
        'market_asset_id', 'quote_currency', 'price', 'market_cap', 'volume_24h',
        'percent_change_1h', 'percent_change_24h', 'percent_change_7d',
        'provider', 'source_updated_at', 'fetched_at',
    ];

    protected function casts(): array
    {
        return [
            'price' => 'decimal:24',
            'market_cap' => 'decimal:8',
            'volume_24h' => 'decimal:8',
            'percent_change_1h' => 'decimal:8',
            'percent_change_24h' => 'decimal:8',
            'percent_change_7d' => 'decimal:8',
            'source_updated_at' => 'immutable_datetime',
            'fetched_at' => 'immutable_datetime',
        ];
    }

    public function asset(): BelongsTo
    {
        return $this->belongsTo(MarketAsset::class, 'market_asset_id');
    }
}

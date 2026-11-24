<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasOne;

final class MarketAsset extends Model
{
    protected $fillable = [
        'provider', 'provider_asset_id', 'name', 'symbol', 'slug',
        'logo_url', 'description', 'website_url', 'tags', 'platform',
        'market_cap_rank', 'is_active', 'date_added', 'metadata_synced_at',
    ];

    protected function casts(): array
    {
        return [
            'tags' => 'array',
            'platform' => 'array',
            'is_active' => 'boolean',
            'date_added' => 'immutable_datetime',
            'metadata_synced_at' => 'immutable_datetime',
        ];
    }

    public function quote(): HasOne
    {
        return $this->hasOne(MarketAssetQuote::class);
    }
}

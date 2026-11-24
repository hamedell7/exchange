<?php

declare(strict_types=1);

namespace App\Http\Resources\Api\V1\Market;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

final class MarketAssetResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'symbol' => $this->symbol,
            'slug' => $this->slug,
            'logo_url' => $this->logo_url,
            'description' => $this->description,
            'website_url' => $this->website_url,
            'tags' => $this->tags,
            'platform' => $this->platform,
            'rank' => $this->market_cap_rank,
            'date_added' => $this->date_added?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }
}

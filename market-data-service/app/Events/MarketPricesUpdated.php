<?php

declare(strict_types=1);

namespace App\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

final class MarketPricesUpdated implements ShouldBroadcastNow
{
    use Dispatchable;
    use SerializesModels;

    /**
     * @param array<int, array<string, int|string|null>> $prices
     */
    public function __construct(
        private readonly array $prices,
        private readonly ?string $usdtTomanRate,
        private readonly ?string $rateUpdatedAt,
        private readonly string $source = 'nobitex',
    ) {}

    /** @return array<int, Channel> */
    public function broadcastOn(): array
    {
        return [new Channel('market.prices')];
    }

    public function broadcastAs(): string
    {
        return 'market.prices.updated';
    }

    /** @return array<string, mixed> */
    public function broadcastWith(): array
    {
        return [
            'source' => $this->source,
            'prices' => $this->prices,
            'market_data' => [
                'usdt_toman_rate' => $this->usdtTomanRate,
                'rate_updated_at' => $this->rateUpdatedAt,
            ],
        ];
    }
}

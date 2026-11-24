<?php

declare(strict_types=1);

namespace App\Services\MarketData;

use Illuminate\Support\Facades\Http;
use RuntimeException;

final class NobitexClient
{
    /**
     * Return the latest USDT price in Toman.
     *
     * Nobitex reports this market in Rials (RLS); one Toman is ten Rials.
     *
     * @return array{rate: string, pair: string}
     */
    public function latestUsdtTomanRate(): array
    {
        $response = Http::baseUrl((string) config('services.nobitex.base_url'))
            ->acceptJson()
            ->connectTimeout(5)
            ->timeout((int) config('services.nobitex.timeout'))
            ->retry(2, 500)
            ->get('/market/stats', [
                'srcCurrency' => 'usdt',
                'dstCurrency' => 'rls',
            ])
            ->throw();

        $payload = $response->json();

        if (! is_array($payload) || strtolower((string) ($payload['status'] ?? '')) !== 'ok') {
            throw new RuntimeException('Nobitex returned an invalid market statistics response.');
        }

        $latestRialPrice = $payload['stats']['usdt-rls']['latest'] ?? null;

        if (! is_numeric($latestRialPrice) || (float) $latestRialPrice <= 0) {
            throw new RuntimeException('Nobitex did not return a valid USDT/RLS price.');
        }

        return [
            'rate' => bcdiv((string) $latestRialPrice, '10', 12),
            'pair' => 'usdt-rls',
        ];
    }
}

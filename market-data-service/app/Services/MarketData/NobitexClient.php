<?php

declare(strict_types=1);

namespace App\Services\MarketData;

use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;
use RuntimeException;

final class NobitexClient
{
    /**
     * Retrieve all public market statistics in one request.
     *
     * @return array<string, array<string, mixed>>
     */
    public function latestMarketStats(): array
    {
        $payload = $this->request()->get('/market/stats')->throw()->json();

        if (
            ! is_array($payload)
            || strtolower((string) ($payload['status'] ?? '')) !== 'ok'
            || ! is_array($payload['stats'] ?? null)
        ) {
            throw new RuntimeException('Nobitex returned an invalid market statistics response.');
        }

        return $payload['stats'];
    }

    private function request(): PendingRequest
    {
        return Http::baseUrl(rtrim((string) config('services.nobitex.base_url'), '/'))
            ->acceptJson()
            ->connectTimeout(5)
            ->timeout((int) config('services.nobitex.timeout', 15))
            ->retry(2, 500);
    }
}

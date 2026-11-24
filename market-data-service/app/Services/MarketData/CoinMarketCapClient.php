<?php

declare(strict_types=1);

namespace App\Services\MarketData;

use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;
use RuntimeException;

final class CoinMarketCapClient
{
    /** @return array<int, array<string, mixed>> */
    public function activeAssetMap(int $start, int $limit): array
    {
        return $this->get('/v1/cryptocurrency/map', [
            'listing_status' => 'active',
            'sort' => 'cmc_rank',
            'start' => $start,
            'limit' => $limit,
            'aux' => 'platform,is_active',
        ]);
    }

    /** @param array<int, int> $providerIds
     *  @return array<string, array<string, mixed>>
     */
    public function metadata(array $providerIds): array
    {
        if ($providerIds === []) {
            return [];
        }

        return $this->get('/v2/cryptocurrency/info', [
            'id' => implode(',', $providerIds),
            'aux' => 'urls,logo,description,tags,platform,date_added,notice,status',
            'skip_invalid' => 'true',
        ]);
    }

    /**
     * Normalize CMC V3's list response and quote list into an ID-keyed map.
     *
     * @param array<int, int> $providerIds
     * @return array<string, array<string, mixed>>
     */
    public function latestUsdtQuotes(array $providerIds): array
    {
        if ($providerIds === []) {
            return [];
        }

        $data = $this->get('/v3/cryptocurrency/quotes/latest', [
            'id' => implode(',', $providerIds),
            'convert' => 'USDT',
            'skip_invalid' => 'true',
        ]);

        $quotesById = [];

        foreach ($data as $key => $asset) {
            if (! is_array($asset)) {
                continue;
            }

            $providerId = (int) ($asset['id'] ?? (is_numeric($key) ? $key : 0));

            if ($providerId <= 0) {
                continue;
            }

            $quotes = $asset['quote'] ?? [];
            $usdtQuote = null;

            // V3 returns quote as a list of conversion objects; legacy responses
            // used an object keyed by the conversion symbol.
            if (is_array($quotes) && array_is_list($quotes)) {
                foreach ($quotes as $quote) {
                    if (is_array($quote) && strtoupper((string) ($quote['symbol'] ?? '')) === 'USDT') {
                        $usdtQuote = $quote;
                        break;
                    }
                }
            } elseif (is_array($quotes)) {
                $usdtQuote = $quotes['USDT'] ?? null;
            }

            if (is_array($usdtQuote)) {
                $asset['quote'] = ['USDT' => $usdtQuote];
                $quotesById[(string) $providerId] = $asset;
            }
        }

        return $quotesById;
    }

    /** @param array<string, mixed> $query
     *  @return array<mixed>
     */
    private function get(string $endpoint, array $query): array
    {
        $apiKey = (string) config('services.coinmarketcap.api_key');

        if ($apiKey === '') {
            throw new RuntimeException('CMC_API_KEY is missing. Set it in market-data-service/.env.');
        }

        $response = $this->request()->get($endpoint, $query)->throw();
        $payload = $response->json();

        if (! is_array($payload)) {
            throw new RuntimeException('CoinMarketCap returned an invalid JSON response.');
        }

        $status = $payload['status'] ?? [];
        $errorCode = $status['error_code'] ?? 0;

        if ((string) $errorCode !== '0') {
            $message = $status['error_message'] ?? 'Unknown CoinMarketCap API error.';
            throw new RuntimeException('CoinMarketCap API error: '.$message);
        }

        $data = $payload['data'] ?? null;

        if (! is_array($data)) {
            throw new RuntimeException('CoinMarketCap response does not contain a valid data payload.');
        }

        return $data;
    }

    private function request(): PendingRequest
    {
        return Http::baseUrl((string) config('services.coinmarketcap.base_url'))
            ->acceptJson()
            ->withHeader('X-CMC_PRO_API_KEY', (string) config('services.coinmarketcap.api_key'))
            ->connectTimeout(5)
            ->timeout((int) config('services.coinmarketcap.timeout'))
            ->retry(3, 500);
    }
}

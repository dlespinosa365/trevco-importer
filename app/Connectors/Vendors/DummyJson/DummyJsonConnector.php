<?php

namespace App\Connectors\Vendors\DummyJson;

use App\Connectors\Contracts\TestsConnection;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use InvalidArgumentException;

/**
 * Demo HTTP client for {@see ConnectorType::DummyJson}. {@see getOrders()} maps to DummyJSON `/carts`
 * (shopping carts used here as a stand-in for “orders” in the public API).
 *
 * Set `base_url` in connector credentials (e.g. https://dummyjson.com).
 *
 * Use via {@see ConnectorsHelper::dummyJson()}.
 */
final class DummyJsonConnector implements TestsConnection
{
    /**
     * @param  array<string, mixed>  $credentials
     */
    public function __construct(private array $credentials) {}

    public function testConnection(): void
    {
        $this->getOrders(['limit' => 1]);
    }

    /**
     * GET carts (used as sample “orders” data). Query params: limit, skip, etc. supported by DummyJSON.
     *
     * @param  array<string, scalar>  $query
     * @return array<string, mixed>
     */
    public function getOrders(array $query = []): array
    {
        $base = $this->baseUrl();
        $url = $base.'/carts';
        $response = Http::timeout((int) config('connectors.http_timeout', 60))
            ->acceptJson()
            ->get($url, $query);

        $response->throw();

        $decoded = $response->json();

        return is_array($decoded) ? $decoded : [];
    }

    private function baseUrl(): string
    {
        Log::info('Dummy JSON connector credentials', $this->credentials);
        $base = $this->credentials['base_url'] ?? null;

        if (! is_string($base) || $base === '') {
            throw new InvalidArgumentException('Dummy JSON connector missing required credential [base_url].');
        }

        return rtrim($base, '/');
    }
}

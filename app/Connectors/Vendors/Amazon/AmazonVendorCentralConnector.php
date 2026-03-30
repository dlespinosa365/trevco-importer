<?php

namespace App\Connectors\Vendors\Amazon;

use App\Integrations\ConnectorsHelper;
use Aws\Credentials\Credentials;
use Aws\Signature\SignatureV4;
use DateTimeInterface;
use GuzzleHttp\Psr7\Request;
use Illuminate\Support\Facades\Http;
use InvalidArgumentException;

/**
 * Amazon Selling Partner API — Vendor Direct Fulfillment purchase orders.
 *
 * {@see getOrders()} calls the official getOrders operation:
 * `GET /vendor/directFulfillment/orders/v1/purchaseOrders` (requires `createdAfter` + `createdBefore`, ISO-8601).
 *
 * @see https://developer-docs.amazon.com/sp-api/docs/vendor-direct-fulfillment-orders-api-v1-reference
 * @see https://github.com/amzn/selling-partner-api-models/blob/main/models/vendor-direct-fulfillment-orders-api-model/vendorDirectFulfillmentOrdersV1.json
 *
 * Use via {@see ConnectorsHelper::amazonVendorCentral()}.
 */
final class AmazonVendorCentralConnector
{
    private const ORDERS_PATH = '/vendor/directFulfillment/orders/v1/purchaseOrders';

    private ?SpApiLwaAccessToken $lwa = null;

    /**
     * @param  array<string, mixed>  $credentials
     */
    public function __construct(private array $credentials) {}

    /**
     * List direct-fulfillment purchase orders in a time window (SP-API getOrders).
     *
     * @param  array{
     *     shipFromPartyId?: string,
     *     status?: 'NEW'|'SHIPPED'|'ACCEPTED'|'CANCELLED',
     *     limit?: int,
     *     sortOrder?: 'ASC'|'DESC',
     *     nextToken?: string,
     *     includeDetails?: bool,
     * }  $options
     * @return array<string, mixed>
     */
    public function getOrders(DateTimeInterface $createdAfter, DateTimeInterface $createdBefore, array $options = []): array
    {
        $creds = $this->assertCredentials();

        $query = [
            'createdAfter' => $createdAfter->format(DateTimeInterface::ATOM),
            'createdBefore' => $createdBefore->format(DateTimeInterface::ATOM),
        ];

        foreach (['shipFromPartyId', 'status', 'limit', 'nextToken', 'sortOrder'] as $key) {
            if (! array_key_exists($key, $options) || $options[$key] === null || $options[$key] === '') {
                continue;
            }
            $query[$key] = is_int($options[$key]) ? $options[$key] : (string) $options[$key];
        }

        if (array_key_exists('includeDetails', $options)) {
            $query['includeDetails'] = $options['includeDetails'] ? 'true' : 'false';
        }

        $base = $this->spApiBaseUrl();
        $uri = $base.self::ORDERS_PATH.'?'.http_build_query($query, '', '&', PHP_QUERY_RFC3986);

        $host = parse_url($base, PHP_URL_HOST);
        if (! is_string($host) || $host === '') {
            throw new InvalidArgumentException('Invalid SP-API base URL.');
        }

        $lwaToken = $this->lwa()->token();

        $psrRequest = new Request('GET', $uri, [
            'host' => $host,
            'x-amz-access-token' => $lwaToken,
            'accept' => 'application/json',
        ]);

        $awsCreds = new Credentials($creds['aws_access_key_id'], $creds['aws_secret_access_key']);
        $signer = new SignatureV4('execute-api', $creds['region']);
        $signed = $signer->signRequest($psrRequest, $awsCreds);

        $headers = [];
        foreach ($signed->getHeaders() as $name => $values) {
            $headers[$name] = $values[0];
        }

        $response = Http::withHeaders($headers)
            ->timeout((int) config('connectors.http_timeout', 60))
            ->get((string) $signed->getUri());

        $response->throw();

        $decoded = $response->json();

        return is_array($decoded) ? $decoded : [];
    }

    /**
     * @return array<string, string>
     */
    private function assertCredentials(): array
    {
        $keys = [
            'client_id',
            'client_secret',
            'refresh_token',
            'aws_access_key_id',
            'aws_secret_access_key',
            'region',
            'sp_api_region',
        ];
        $out = [];
        foreach ($keys as $key) {
            $v = $this->credentials[$key] ?? null;
            if (! is_string($v) || $v === '') {
                throw new InvalidArgumentException("Amazon Vendor Central connector missing required credential [{$key}].");
            }
            $out[$key] = $v;
        }

        $r = $out['sp_api_region'];
        if (! in_array($r, ['na', 'eu', 'fe'], true)) {
            throw new InvalidArgumentException('sp_api_region must be na, eu, or fe (Selling Partner API endpoint).');
        }

        return $out;
    }

    private function lwa(): SpApiLwaAccessToken
    {
        $c = $this->assertCredentials();

        return $this->lwa ??= new SpApiLwaAccessToken([
            'client_id' => $c['client_id'],
            'client_secret' => $c['client_secret'],
            'refresh_token' => $c['refresh_token'],
        ]);
    }

    private function spApiBaseUrl(): string
    {
        $host = match ($this->assertCredentials()['sp_api_region']) {
            'na' => 'sellingpartnerapi-na.amazon.com',
            'eu' => 'sellingpartnerapi-eu.amazon.com',
            'fe' => 'sellingpartnerapi-fe.amazon.com',
        };

        return 'https://'.$host;
    }
}

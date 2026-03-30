<?php

namespace App\Connectors\Vendors\NetSuite;

use App\Connectors\Contracts\TestsConnection;
use App\Integrations\ConnectorsHelper;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use InvalidArgumentException;
use Throwable;

/**
 * NetSuite REST connector (SuiteTalk). Uses token-based authentication (OAuth 1.0a TBA).
 *
 * Credentials: account_id, client_id (Consumer Key), client_secret (Consumer Secret),
 * token_id, token_secret, restlet_url — as shown in NetSuite for your integration / access token.
 *
 * `account_id` may use NetSuite’s display form (e.g. `3521821_SB1`); the SuiteTalk host
 * normalizes to lowercase with underscores as hyphens (e.g. `3521821-sb1.suitetalk.api.netsuite.com`).
 * The OAuth `realm` still uses the stored `account_id` string.
 *
 * Use via {@see ConnectorsHelper::netsuite()}.
 *
 * @see https://docs.oracle.com/en/cloud/saas/netsuite/ns-online-help/chapter_4247329078.html
 */
final class NetSuiteConnector implements TestsConnection
{
    /**
     * @param  array<string, mixed>  $credentials
     */
    public function __construct(private array $credentials) {}

    public function testConnection(): void
    {
        $c = $this->assertCredentials();
        $accountId = $c['account_id'];
        $hostSegment = self::normalizeSuiteTalkHostSegment($accountId);

        Log::info('netsuite.test_connection.start', [
            'base_url' => $this->baseUrl(),
            'suiteql_path' => '/services/rest/query/v1/suiteql',
            'oauth_realm' => self::redactAccountIdForLog($accountId),
            'host_segment' => $hostSegment,
            'account_id_as_host_label' => strtolower(str_replace('_', '-', $accountId)),
            'oauth_timestamp_utc' => time(),
        ]);

        try {
            $this->executeQuery('SELECT 1 AS n');
            Log::info('netsuite.test_connection.success');
        } catch (Throwable $e) {
            Log::error('netsuite.test_connection.failed', [
                'exception' => $e::class,
                'message' => $e->getMessage(),
                'previous' => $e->getPrevious() ? [
                    'class' => $e->getPrevious()::class,
                    'message' => $e->getPrevious()->getMessage(),
                ] : null,
            ]);
            throw $e;
        }
    }

    /**
     * Creates a transaction via the RESTlet using standard record mode (`isDynamic: false` on the SuiteScript side).
     * Any `isDynamic` key in `bodyParams` is removed and cannot override that behavior.
     *
     * @param  array<string, mixed>  $order
     * @return array<string, mixed>
     */
    public function createOrder(array $order): array
    {
        $bodyParams = $order['bodyParams'] ?? $order;
        if (! is_array($bodyParams)) {
            throw new InvalidArgumentException('createOrder expects bodyParams as an array.');
        }

        if (! array_key_exists('bodyParams', $order)) {
            unset($bodyParams['item'], $bodyParams['lineParams'], $bodyParams['type']);
        }

        $lineParams = $order['lineParams'] ?? ($order['item']['items'] ?? []);
        if (! is_array($lineParams)) {
            throw new InvalidArgumentException('createOrder expects lineParams as an array.');
        }

        unset($bodyParams['isDynamic']);

        return $this->requestRestletJson('POST', [
            'action' => 'createTransaction',
            'type' => (string) ($order['type'] ?? 'salesorder'),
            'bodyParams' => $bodyParams,
            'lineParams' => $lineParams,
        ]);
    }

    /**
     * Run SuiteQL (POST /services/rest/query/v1/suiteql).
     *
     * @return array<string, mixed>
     */
    public function executeQuery(string $suiteQl): array
    {
        return $this->requestJson('POST', '/services/rest/query/v1/suiteql', ['q' => $suiteQl]);
    }

    /**
     * Execute a saved search through the RESTlet.
     *
     * @return array<string, mixed>
     */
    public function runSavedSearch(string $searchId, string $type): array
    {
        return $this->requestRestletJson('POST', [
            'action' => 'runSavedSearch',
            'searchId' => $searchId,
            'type' => $type,
        ]);
    }

    /**
     * PATCH a record by internal id.
     *
     * @param  array<string, mixed>  $fields
     * @return array<string, mixed>
     */
    public function updateRecord(string $recordType, string $internalId, array $fields): array
    {
        return $this->requestRestletJson('POST', [
            'action' => 'updateRecord',
            'recordType' => $recordType,
            'id' => $internalId,
            'fields' => $fields,
        ]);
    }

    /**
     * GET a single record (field values).
     *
     * @return array<string, mixed>
     */
    public function getRecordFields(string $recordType, string $internalId, array $fields = []): array
    {
        return $this->requestRestletJson('POST', [
            'action' => 'getRecordFields',
            'recordType' => $recordType,
            'id' => $internalId,
            'fields' => array_values($fields),
        ]);
    }

    /**
     * @return array<string, string>
     */
    private function assertCredentials(): array
    {
        $keys = ['account_id', 'client_id', 'client_secret', 'token_id', 'token_secret'];
        $out = [];
        foreach ($keys as $key) {
            $v = $this->credentials[$key] ?? null;
            if (! is_string($v) || $v === '') {
                throw new InvalidArgumentException("NetSuite connector missing required credential [{$key}].");
            }
            $out[$key] = $v;
        }

        return $out;
    }

    /**
     * @return array<string, string>
     */
    private function tbaCredentials(): array
    {
        $c = $this->assertCredentials();

        return [
            'account_id' => $c['account_id'],
            'consumer_key' => $c['client_id'],
            'consumer_secret' => $c['client_secret'],
            'token_id' => $c['token_id'],
            'token_secret' => $c['token_secret'],
        ];
    }

    private function baseUrl(): string
    {
        $hostSegment = self::normalizeSuiteTalkHostSegment($this->assertCredentials()['account_id']);

        return 'https://'.$hostSegment.'.suitetalk.api.netsuite.com';
    }

    /**
     * SuiteTalk REST hosts use a DNS label: underscores from Account ID become hyphens; label is lowercased.
     */
    private static function normalizeSuiteTalkHostSegment(string $accountId): string
    {
        return strtolower(str_replace('_', '-', $accountId));
    }

    /**
     * @param  array<string, mixed>|null  $json
     * @return array<string, mixed>
     */
    private function requestJson(string $method, string $path, ?array $json): array
    {
        $url = $this->baseUrl().$path;
        $queryParams = $method === 'GET' ? ($json ?? []) : [];
        $body = $method === 'GET' || $json === null ? null : json_encode($json, JSON_THROW_ON_ERROR);

        $auth = OAuth1Tba::authorizationHeader($method, $url, $this->tbaCredentials(), $body, $queryParams);

        $headers = [
            'Authorization' => $auth,
            'Accept' => 'application/json',
        ];

        if ($method === 'POST' && $path === '/services/rest/query/v1/suiteql') {
            $headers['Prefer'] = 'transient';
        }

        $pending = Http::withHeaders($headers)
            ->timeout((int) config('connectors.http_timeout', 60))
            ->dontTruncateExceptions();

        $response = match ($method) {
            'GET' => $pending->get($url, $queryParams),
            'POST' => $body !== null
                ? $pending->withBody($body, 'application/json')->post($url)
                : $pending->post($url),
            'PATCH' => $pending->withBody($body ?? '{}', 'application/json')->patch($url),
            default => throw new InvalidArgumentException("Unsupported HTTP method [{$method}] for NetSuite."),
        };

        if ($response->failed()) {
            Log::warning('netsuite.http.error_response', [
                'method' => $method,
                'path' => $path,
                'url' => $url,
                'status' => $response->status(),
                'reason' => $response->reason(),
                'response_headers' => self::redactResponseHeaders($response->headers()),
                'body' => Str::limit($response->body(), 8000),
            ]);
        }

        return $this->decodeResponse($response);
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    private function requestRestletJson(string $method, array $payload): array
    {
        $url = $this->restletUrl();
        $body = json_encode($payload, JSON_THROW_ON_ERROR);
        $queryParams = $this->extractUrlQueryParams($url);

        $auth = OAuth1Tba::authorizationHeader($method, $url, $this->tbaCredentials(), $body, $queryParams);

        $response = Http::withHeaders([
            'Authorization' => $auth,
            'Accept' => 'application/json',
            'Content-Type' => 'application/json',
        ])
            ->timeout((int) config('connectors.http_timeout', 60))
            ->dontTruncateExceptions()
            ->withBody($body, 'application/json')
            ->send($method, $url);

        if ($response->failed()) {
            Log::warning('netsuite.restlet.error_response', [
                'method' => $method,
                'url' => $url,
                'status' => $response->status(),
                'reason' => $response->reason(),
                'response_headers' => self::redactResponseHeaders($response->headers()),
                'body' => Str::limit($response->body(), 8000),
            ]);
        }

        return $this->decodeResponse($response);
    }

    /**
     * @return array<string, string>
     */
    private function extractUrlQueryParams(string $url): array
    {
        $query = parse_url($url, PHP_URL_QUERY);
        if (! is_string($query) || $query === '') {
            return [];
        }

        $params = [];
        parse_str($query, $params);

        $flattened = [];
        foreach ($params as $key => $value) {
            if (is_scalar($value) || $value === null) {
                $flattened[(string) $key] = (string) $value;
            }
        }

        return $flattened;
    }

    private function restletUrl(): string
    {
        $url = $this->credentials['restlet_url'] ?? null;
        if (! is_string($url) || trim($url) === '') {
            throw new InvalidArgumentException('NetSuite connector missing required credential [restlet_url].');
        }

        return trim($url);
    }

    /**
     * @return array<string, mixed>
     */
    private function decodeResponse(Response $response): array
    {
        $response->throw();

        $decoded = $response->json();

        return is_array($decoded) ? $decoded : [];
    }

    /**
     * @param  array<string, list<string|null>>  $headers
     * @return array<string, list<string|null>>
     */
    private static function redactResponseHeaders(array $headers): array
    {
        unset($headers['set-cookie']);

        return $headers;
    }

    private static function redactAccountIdForLog(string $accountId): string
    {
        $len = strlen($accountId);
        if ($len <= 8) {
            return '*** ('.$len.' chars)';
        }

        return substr($accountId, 0, 4).'…'.substr($accountId, -4).' ('.$len.' chars)';
    }
}

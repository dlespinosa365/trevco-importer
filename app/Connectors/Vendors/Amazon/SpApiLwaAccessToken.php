<?php

namespace App\Connectors\Vendors\Amazon;

use Illuminate\Support\Facades\Http;
use RuntimeException;

/**
 * Login with Amazon (LWA) access token for Selling Partner API (refresh_token grant).
 *
 * @see https://developer-docs.amazon.com/sp-api/docs/connecting-to-the-selling-partner-api
 */
final class SpApiLwaAccessToken
{
    private ?string $accessToken = null;

    private int $expiresAt = 0;

    /**
     * @param  array<string, string>  $credentials  client_id, client_secret, refresh_token
     */
    public function __construct(private array $credentials) {}

    public function token(): string
    {
        if ($this->accessToken !== null && $this->expiresAt > time() + 60) {
            return $this->accessToken;
        }

        $response = Http::asForm()
            ->acceptJson()
            ->timeout((int) config('connectors.http_timeout', 60))
            ->post('https://api.amazon.com/auth/o2/token', [
                'grant_type' => 'refresh_token',
                'refresh_token' => $this->credentials['refresh_token'],
                'client_id' => $this->credentials['client_id'],
                'client_secret' => $this->credentials['client_secret'],
            ]);

        $response->throw();

        /** @var array<string, mixed> $data */
        $data = $response->json() ?? [];
        $token = $data['access_token'] ?? null;
        if (! is_string($token) || $token === '') {
            throw new RuntimeException('Amazon LWA token response missing access_token.');
        }

        $this->accessToken = $token;
        $this->expiresAt = time() + (int) ($data['expires_in'] ?? 3600);

        return $this->accessToken;
    }
}

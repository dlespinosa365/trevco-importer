<?php

namespace App\Connectors\Vendors\NetSuite;

use InvalidArgumentException;

/**
 * OAuth 1.0a TBA Authorization header for NetSuite REST / SuiteTalk.
 *
 * @see https://docs.oracle.com/en/cloud/saas/netsuite/ns-online-help/article_0109079767.html
 */
final class OAuth1Tba
{
    /**
     * @param  array<string, string>  $credentials  account_id, consumer_key, consumer_secret, token_id, token_secret
     */
    public static function authorizationHeader(
        string $method,
        string $url,
        array $credentials,
        ?string $body = null,
        array $queryParams = [],
    ): string {
        $method = strtoupper($method);
        $parts = parse_url($url);
        if ($parts === false || ! isset($parts['scheme'], $parts['host'])) {
            throw new InvalidArgumentException('Invalid URL for NetSuite OAuth signing.');
        }

        $scheme = strtolower($parts['scheme']);
        $host = strtolower($parts['host']);
        $path = $parts['path'] ?? '/';
        $port = $parts['port'] ?? null;
        $defaultPort = $scheme === 'https' ? 443 : 80;
        $includePort = $port !== null && (int) $port !== $defaultPort;
        $authority = $host.($includePort ? ':'.$port : '');
        $normalizedUrl = $scheme.'://'.$authority.$path;

        $oauth = [
            'oauth_consumer_key' => $credentials['consumer_key'],
            'oauth_nonce' => bin2hex(random_bytes(16)),
            'oauth_signature_method' => 'HMAC-SHA256',
            'oauth_timestamp' => (string) time(),
            'oauth_token' => $credentials['token_id'],
            'oauth_version' => '1.0',
        ];

        if ($body !== null && $body !== '') {
            $oauth['oauth_body_hash'] = base64_encode(hash('sha256', $body, true));
        }

        $signingParams = array_merge($queryParams, $oauth);
        ksort($signingParams);

        $paramPairs = [];
        foreach ($signingParams as $k => $v) {
            $paramPairs[] = self::rfc3986Encode((string) $k).'='.self::rfc3986Encode((string) $v);
        }
        $paramString = implode('&', $paramPairs);

        $baseString = $method.'&'.self::rfc3986Encode($normalizedUrl).'&'.self::rfc3986Encode($paramString);

        $signingKey = $credentials['consumer_secret'].'&'.$credentials['token_secret'];
        $signature = base64_encode(hash_hmac('sha256', $baseString, $signingKey, true));

        $oauth['oauth_signature'] = $signature;

        $headerParams = [];
        foreach ($oauth as $k => $v) {
            $headerParams[] = $k.'="'.self::rfc3986Encode((string) $v).'"';
        }

        $realm = self::escapeRealmValue($credentials['account_id']);

        return 'OAuth realm="'.$realm.'", '.implode(', ', $headerParams);
    }

    private static function escapeRealmValue(string $realm): string
    {
        return str_replace(['\\', '"'], ['\\\\', '\\"'], $realm);
    }

    private static function rfc3986Encode(string $value): string
    {
        return rawurlencode($value);
    }
}

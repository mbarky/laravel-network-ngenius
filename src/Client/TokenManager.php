<?php

declare(strict_types=1);

namespace mbarky\Ngenius\Client;

use Illuminate\Contracts\Cache\Repository as CacheRepository;
use Illuminate\Http\Client\Factory as HttpFactory;
use Illuminate\Http\Client\RequestException;
use mbarky\Ngenius\Enums\Environment;
use mbarky\Ngenius\Exceptions\AuthenticationException;

/**
 * Manages N-Genius access tokens with automatic caching and refresh.
 *
 * Tokens are valid for 300 s. We cache for 270 s (configurable safety margin)
 * so we never present an expired token to the API.
 */
final class TokenManager
{
    public function __construct(
        private readonly CacheRepository $cache,
        private readonly HttpFactory $httpClient,
    ) {}

    /**
     * Return a valid access token, retrieving a fresh one if the cache is cold.
     *
     * @throws AuthenticationException
     */
    public function getToken(): string
    {
        $env = Environment::fromConfig();
        $rawKey = config('ngenius.cache.token_key', 'ngenius_access_token');
        $cacheKey = is_string($rawKey) ? $rawKey : 'ngenius_access_token';
        $rawTtl = config('ngenius.cache.ttl_seconds', 270);
        $ttl = is_int($rawTtl) ? $rawTtl : 270;

        /** @var string|null $cached */
        $cached = $this->cache->get($cacheKey);

        if (is_string($cached) && $cached !== '') {
            return $cached;
        }

        return $this->fetchAndCacheToken($env, $cacheKey, $ttl);
    }

    /** Force a fresh token fetch, bypassing the cache (e.g. after a 401). */
    public function forgetToken(): void
    {
        $raw = config('ngenius.cache.token_key', 'ngenius_access_token');
        $cacheKey = is_string($raw) ? $raw : 'ngenius_access_token';
        $this->cache->forget($cacheKey);
    }

    /** @throws AuthenticationException */
    private function fetchAndCacheToken(Environment $env, string $cacheKey, int $ttl): string
    {
        $apiKey = $env->apiKey();

        // Never log the API key.
        try {
            $response = $this->httpClient
                ->withHeaders([
                    'Content-Type' => 'application/vnd.ni-identity.v1+json',
                    'Authorization' => 'Basic '.$apiKey,
                ])
                ->timeout(15)
                ->retry(2, 500)
                ->post($env->baseUrl().'/identity/auth/access-token');
        } catch (RequestException $e) {
            throw new AuthenticationException(
                "N-Genius authentication failed (HTTP {$e->response->status()}).",
                $e->response->status(),
                $e
            );
        }

        if (! $response->successful()) {
            throw new AuthenticationException(
                "N-Genius authentication failed (HTTP {$response->status()})."
            );
        }

        $body = $response->json();
        if (! is_array($body)) {
            throw new AuthenticationException('N-Genius authentication returned invalid JSON response.');
        }

        $token = data_get($body, 'access_token');

        if (! is_string($token) || $token === '') {
            throw new AuthenticationException(
                'N-Genius authentication response did not contain an access_token.'
            );
        }

        // Cache for the configured TTL (default 270 s, never logs the token value).
        $this->cache->put($cacheKey, $token, $ttl);

        return $token;
    }
}

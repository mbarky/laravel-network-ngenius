<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Http;
use mbarky\Ngenius\Client\TokenManager;
use mbarky\Ngenius\Exceptions\AuthenticationException;

beforeEach(function () {
    // Reset token cache between tests
    app('cache')->store('array')->flush();
});

it('fetches a fresh token when the cache is empty', function () {
    Http::fake([
        '*/identity/auth/access-token' => Http::response(
            ['access_token' => 'fresh-token-abc', 'refresh_token' => 'refresh-xyz'],
            200
        ),
    ]);

    $manager = app(TokenManager::class);
    $token = $manager->getToken();

    expect($token)->toBe('fresh-token-abc');
    Http::assertSentCount(1);
});

it('returns the cached token without making a second HTTP request', function () {
    Http::fake([
        '*/identity/auth/access-token' => Http::sequence()
            ->push(['access_token' => 'cached-token', 'refresh_token' => 'r'], 200)
            ->push(['access_token' => 'should-not-be-called', 'refresh_token' => 'r'], 200),
    ]);

    $manager = app(TokenManager::class);

    $first = $manager->getToken();
    $second = $manager->getToken();

    expect($first)->toBe('cached-token')
        ->and($second)->toBe('cached-token');

    Http::assertSentCount(1); // Only one HTTP call despite two getToken() calls
});

it('refreshes the token after forgetToken() is called', function () {
    Http::fake([
        '*/identity/auth/access-token' => Http::sequence()
            ->push(['access_token' => 'first-token', 'refresh_token' => 'r'], 200)
            ->push(['access_token' => 'second-token', 'refresh_token' => 'r'], 200),
    ]);

    $manager = app(TokenManager::class);

    $first = $manager->getToken();
    $manager->forgetToken();
    $second = $manager->getToken();

    expect($first)->toBe('first-token')
        ->and($second)->toBe('second-token');

    Http::assertSentCount(2);
});

it('throws AuthenticationException when the API returns a non-2xx status', function () {
    Http::fake([
        '*/identity/auth/access-token' => Http::response(['error' => 'Unauthorized'], 401),
    ]);

    $manager = app(TokenManager::class);

    expect(fn () => $manager->getToken())
        ->toThrow(AuthenticationException::class);
});

it('throws AuthenticationException when the response has no access_token field', function () {
    Http::fake([
        '*/identity/auth/access-token' => Http::response(['token_type' => 'Bearer'], 200),
    ]);

    $manager = app(TokenManager::class);

    expect(fn () => $manager->getToken())
        ->toThrow(AuthenticationException::class, 'access_token');
});

it('sends the correct Content-Type and Authorization headers', function () {
    Http::fake([
        '*/identity/auth/access-token' => Http::response(
            ['access_token' => 'hdr-test-token', 'refresh_token' => 'r'],
            200
        ),
    ]);

    $manager = app(TokenManager::class);
    $manager->getToken();

    Http::assertSent(function ($request) {
        return $request->hasHeader('Content-Type', 'application/vnd.ni-identity.v1+json')
            && str_starts_with((string) $request->header('Authorization')[0], 'Basic ');
    });
});

it('caches the token with the configured TTL key', function () {
    Http::fake([
        '*/identity/auth/access-token' => Http::response(
            ['access_token' => 'ttl-test-token', 'refresh_token' => 'r'],
            200
        ),
    ]);

    $manager = app(TokenManager::class);
    $manager->getToken();

    $cacheKey = config('ngenius.cache.token_key');
    $cached = app('cache')->store('array')->get($cacheKey);

    expect($cached)->toBe('ttl-test-token');
});

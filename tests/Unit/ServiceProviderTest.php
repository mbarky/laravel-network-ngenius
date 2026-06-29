<?php

declare(strict_types=1);

use mbarky\Ngenius\Client\NgeniusClient;
use mbarky\Ngenius\Client\TokenManager;
use mbarky\Ngenius\Contracts\NgeniusClientContract;
use mbarky\Ngenius\Facades\Ngenius;
use mbarky\Ngenius\Services\PaymentService;

it('boots the service provider and resolves PaymentService', function () {
    $service = app(PaymentService::class);

    expect($service)->toBeInstanceOf(PaymentService::class);
});

it('resolves NgeniusClientContract to NgeniusClient', function () {
    $client = app(NgeniusClientContract::class);

    expect($client)->toBeInstanceOf(NgeniusClient::class);
});

it('resolves TokenManager as a singleton', function () {
    $a = app(TokenManager::class);
    $b = app(TokenManager::class);

    expect($a)->toBe($b);
});

it('publishes config with the correct key', function () {
    $config = config('ngenius');

    expect($config)->toBeArray()
        ->and($config)->toHaveKey('environment')
        ->and($config)->toHaveKey('sandbox')
        ->and($config)->toHaveKey('production')
        ->and($config)->toHaveKey('webhook')
        ->and($config)->toHaveKey('cache');
});

it('resolves the Ngenius facade to a PaymentService', function () {
    // Facade::getFacadeAccessor() is protected; test via the container binding instead.
    $accessor = (new ReflectionMethod(Ngenius::class, 'getFacadeAccessor'))
        ->invoke(null);

    $service = app($accessor);

    expect($service)->toBeInstanceOf(PaymentService::class);
});

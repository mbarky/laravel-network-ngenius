<?php

declare(strict_types=1);

use mbarky\Ngenius\Client\NgeniusResponse;
use mbarky\Ngenius\Exceptions\NgeniusException;

it('extracts a HAL link href via link()', function () {
    $response = NgeniusResponse::fromArray([
        'reference' => 'ORD-001',
        '_links' => [
            'payment' => ['href' => 'https://hpp.example.com/pay/ORD-001'],
        ],
    ], 200);

    expect($response->link('payment'))->toBe('https://hpp.example.com/pay/ORD-001');
});

it('throws NgeniusException when a required link is absent', function () {
    $response = NgeniusResponse::fromArray(['reference' => 'ORD-001'], 200);

    expect(fn () => $response->link('payment'))
        ->toThrow(NgeniusException::class, "HAL link 'payment' not found");
});

it('returns null from linkOrNull() when link is absent', function () {
    $response = NgeniusResponse::fromArray([], 200);

    expect($response->linkOrNull('missing'))->toBeNull();
});

it('reports successful() correctly for 2xx statuses', function () {
    expect(NgeniusResponse::fromArray([], 200)->successful())->toBeTrue()
        ->and(NgeniusResponse::fromArray([], 201)->successful())->toBeTrue()
        ->and(NgeniusResponse::fromArray([], 400)->successful())->toBeFalse()
        ->and(NgeniusResponse::fromArray([], 500)->successful())->toBeFalse();
});

it('throw() raises NgeniusException on non-2xx', function () {
    $response = NgeniusResponse::fromArray(['error' => 'bad'], 422);

    expect(fn () => $response->throw())
        ->toThrow(NgeniusException::class);
});

it('get() retrieves nested values using dot notation', function () {
    $response = NgeniusResponse::fromArray([
        'amount' => ['currencyCode' => 'AED', 'value' => 25000],
    ], 200);

    expect($response->get('amount.currencyCode'))->toBe('AED')
        ->and($response->get('amount.value'))->toBe(25000)
        ->and($response->get('amount.missing', 'default'))->toBe('default');
});

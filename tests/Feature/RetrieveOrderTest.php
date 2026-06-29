<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Http;
use mbarky\Ngenius\DTO\NgeniusOrder;
use mbarky\Ngenius\Enums\PaymentState;
use mbarky\Ngenius\Exceptions\PaymentVerificationException;
use mbarky\Ngenius\Services\PaymentService;

function fakeRetrieveOrder(string $state = 'PURCHASED', string $ref = 'ORD-TEST-001'): void
{
    Http::fake([
        '*/identity/auth/access-token' => Http::response(
            ['access_token' => 'unit-test-token', 'refresh_token' => 'r'],
            200
        ),
        "*/orders/{$ref}" => Http::response([
            'reference' => $ref,
            'action' => 'PURCHASE',
            'amount' => ['currencyCode' => 'AED', 'value' => 25000],
            '_embedded' => ['payment' => [['state' => $state]]],
            '_links' => [
                'self' => ['href' => "https://api-gateway.sandbox.ngenius-payments.com/transactions/outlets/test-outlet-ref/orders/{$ref}"],
            ],
        ], 200),
    ]);
}

beforeEach(fn () => app('cache')->store('array')->flush());

// ------------------------------------------------------------------
// Happy-path state mapping
// ------------------------------------------------------------------

it('retrieves a PURCHASED order and reports isPaid() = true', function () {
    fakeRetrieveOrder('PURCHASED');

    $order = app(PaymentService::class)->retrieveOrder('ORD-TEST-001');

    expect($order)->toBeInstanceOf(NgeniusOrder::class)
        ->and($order->state)->toBe(PaymentState::Purchased)
        ->and($order->isPaid())->toBeTrue()
        ->and($order->isFailed())->toBeFalse();
});

it('retrieves a CAPTURED order and reports isPaid() = true', function () {
    fakeRetrieveOrder('CAPTURED');

    $order = app(PaymentService::class)->retrieveOrder('ORD-TEST-001');

    expect($order->state)->toBe(PaymentState::Captured)
        ->and($order->isPaid())->toBeTrue();
});

it('retrieves a FAILED order and reports isFailed() = true', function () {
    fakeRetrieveOrder('FAILED');

    $order = app(PaymentService::class)->retrieveOrder('ORD-TEST-001');

    expect($order->state)->toBe(PaymentState::Failed)
        ->and($order->isPaid())->toBeFalse()
        ->and($order->isFailed())->toBeTrue();
});

it('retrieves a DECLINED order and reports isFailed() = true', function () {
    fakeRetrieveOrder('DECLINED');

    $order = app(PaymentService::class)->retrieveOrder('ORD-TEST-001');

    expect($order->isFailed())->toBeTrue();
});

it('retrieves a STARTED order and reports neither paid nor failed', function () {
    fakeRetrieveOrder('STARTED');

    $order = app(PaymentService::class)->retrieveOrder('ORD-TEST-001');

    expect($order->isPaid())->toBeFalse()
        ->and($order->isFailed())->toBeFalse()
        ->and($order->isTerminal())->toBeFalse();
});

// ------------------------------------------------------------------
// Retrieve uses the correct endpoint (HAL-conscious)
// ------------------------------------------------------------------

it('calls GET /transactions/outlets/{outlet}/orders/{ref}', function () {
    fakeRetrieveOrder('PURCHASED', 'ORD-SPECIFIC-REF');

    app(PaymentService::class)->retrieveOrder('ORD-SPECIFIC-REF');

    Http::assertSent(function ($request) {
        return str_contains($request->url(), '/orders/ORD-SPECIFIC-REF')
            && $request->method() === 'GET';
    });
});

it('sends the Bearer token and Accept header on retrieval', function () {
    fakeRetrieveOrder('PURCHASED');

    app(PaymentService::class)->retrieveOrder('ORD-TEST-001');

    Http::assertSent(function ($request) {
        return str_contains($request->url(), '/orders/')
            && $request->hasHeader('Authorization', 'Bearer unit-test-token')
            && $request->hasHeader('Accept', 'application/vnd.ni-payment.v2+json');
    });
});

// ------------------------------------------------------------------
// Error path
// ------------------------------------------------------------------

it('throws PaymentVerificationException on API error', function () {
    Http::fake([
        '*/identity/auth/access-token' => Http::response(
            ['access_token' => 'token', 'refresh_token' => 'r'], 200
        ),
        '*/orders/MISSING-REF' => Http::response(['error' => 'Not Found'], 404),
    ]);

    expect(fn () => app(PaymentService::class)->retrieveOrder('MISSING-REF'))
        ->toThrow(PaymentVerificationException::class);
});

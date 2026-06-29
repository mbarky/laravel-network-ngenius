<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Http;
use mbarky\Ngenius\DTO\CreateOrderData;
use mbarky\Ngenius\DTO\Money;
use mbarky\Ngenius\DTO\NgeniusOrder;
use mbarky\Ngenius\Enums\PaymentAction;
use mbarky\Ngenius\Enums\PaymentState;
use mbarky\Ngenius\Exceptions\NgeniusException;
use mbarky\Ngenius\Exceptions\OrderCreationException;
use mbarky\Ngenius\Services\PaymentService;

// Helper that fakes both the auth token endpoint and the create-order endpoint.
function fakeCreateOrder(array $orderBody = [], int $orderStatus = 201): void
{
    $defaultOrderBody = [
        'reference' => 'ORD-TEST-001',
        'action' => 'PURCHASE',
        'amount' => ['currencyCode' => 'AED', 'value' => 25000],
        '_embedded' => ['payment' => [['state' => 'STARTED']]],
        '_links' => [
            'payment' => ['href' => 'https://hpp.sandbox.ngenius-payments.com/pay/ORD-TEST-001'],
            'self' => ['href' => 'https://api-gateway.sandbox.ngenius-payments.com/transactions/outlets/test-outlet-ref/orders/ORD-TEST-001'],
        ],
    ];

    Http::fake([
        '*/identity/auth/access-token' => Http::response(
            ['access_token' => 'unit-test-token', 'refresh_token' => 'r'],
            200
        ),
        '*/transactions/outlets/*/orders' => Http::response(
            array_merge($defaultOrderBody, $orderBody),
            $orderStatus
        ),
    ]);
}

beforeEach(function () {
    app('cache')->store('array')->flush();
});

// ------------------------------------------------------------------
// Happy path
// ------------------------------------------------------------------

it('creates an order and returns a NgeniusOrder with payment URL', function () {
    fakeCreateOrder();

    $service = app(PaymentService::class);
    $data = CreateOrderData::make()
        ->amount(Money::aed(250.00))
        ->email('buyer@example.com')
        ->merchantOrderReference('INV-001')
        ->redirectUrl('https://example.com/callback');

    $order = $service->createPayment($data);

    expect($order)->toBeInstanceOf(NgeniusOrder::class)
        ->and($order->orderReference)->toBe('ORD-TEST-001')
        ->and($order->paymentUrl)->toBe('https://hpp.sandbox.ngenius-payments.com/pay/ORD-TEST-001')
        ->and($order->state)->toBe(PaymentState::Started)
        ->and($order->isPaid())->toBeFalse();
});

// ------------------------------------------------------------------
// Payload shape
// ------------------------------------------------------------------

it('sends the correct JSON payload to the create-order endpoint', function () {
    fakeCreateOrder();

    $service = app(PaymentService::class);
    $data = CreateOrderData::make()
        ->amount(Money::aed(250.00))
        ->email('customer@example.com')
        ->merchantOrderReference('INV-999')
        ->redirectUrl('https://example.com/return')
        ->action(PaymentAction::Purchase);

    $service->createPayment($data);

    Http::assertSent(function ($request) {
        if (! str_contains($request->url(), '/orders')) {
            return false;
        }

        $body = $request->data();

        return $body['action'] === 'PURCHASE'
            && $body['amount']['currencyCode'] === 'AED'
            && $body['amount']['value'] === 25000          // minor units!
            && $body['emailAddress'] === 'customer@example.com'
            && $body['merchantOrderReference'] === 'INV-999'
            && $body['redirectUrl'] === 'https://example.com/return';
    });
});

it('sends amount.value as an integer minor unit, not a float', function () {
    fakeCreateOrder();

    $service = app(PaymentService::class);
    $service->createPayment(
        CreateOrderData::make()->amount(Money::aed(250.00))->email('x@example.com')
    );

    Http::assertSent(function ($request) {
        if (! str_contains($request->url(), '/orders')) {
            return false;
        }

        $value = $request->data()['amount']['value'] ?? null;

        return is_int($value);
    });
});

it('sends the Bearer token in the Authorization header', function () {
    fakeCreateOrder();

    $service = app(PaymentService::class);
    $service->createPayment(
        CreateOrderData::make()->amount(Money::aed(10.00))->email('x@example.com')
    );

    Http::assertSent(function ($request) {
        return str_contains($request->url(), '/orders')
            && $request->hasHeader('Authorization', 'Bearer unit-test-token');
    });
});

it('sends the correct Content-Type and Accept headers', function () {
    fakeCreateOrder();

    $service = app(PaymentService::class);
    $service->createPayment(
        CreateOrderData::make()->amount(Money::aed(10.00))->email('x@example.com')
    );

    Http::assertSent(function ($request) {
        return str_contains($request->url(), '/orders')
            && $request->hasHeader('Content-Type', 'application/vnd.ni-payment.v2+json')
            && $request->hasHeader('Accept', 'application/vnd.ni-payment.v2+json');
    });
});

// ------------------------------------------------------------------
// URL extraction (HAL-first)
// ------------------------------------------------------------------

it('extracts payment URL from _links.payment.href in the response', function () {
    fakeCreateOrder(['_links' => [
        'payment' => ['href' => 'https://hpp.example.com/unique-link'],
    ]]);

    $service = app(PaymentService::class);
    $order = $service->createPayment(
        CreateOrderData::make()->amount(Money::aed(10.00))->email('x@example.com')
    );

    expect($order->paymentUrl)->toBe('https://hpp.example.com/unique-link');
});

it('throws OrderCreationException when _links.payment is absent', function () {
    Http::fake([
        '*/identity/auth/access-token' => Http::response(
            ['access_token' => 'token', 'refresh_token' => 'r'], 200
        ),
        '*/transactions/outlets/*/orders' => Http::response([
            'reference' => 'ORD-NO-LINK',
            'amount' => ['currencyCode' => 'AED', 'value' => 1000],
            '_embedded' => ['payment' => [['state' => 'STARTED']]],
            '_links' => [],
        ], 201),
    ]);

    app('cache')->store('array')->flush();
    $service = app(PaymentService::class);

    expect(fn () => $service->createPayment(
        CreateOrderData::make()->amount(Money::aed(10.00))->email('x@example.com')
    ))->toThrow(NgeniusException::class);
});

// ------------------------------------------------------------------
// Error paths
// ------------------------------------------------------------------

it('throws OrderCreationException on API error response', function () {
    Http::fake([
        '*/identity/auth/access-token' => Http::response(
            ['access_token' => 'token', 'refresh_token' => 'r'], 200
        ),
        '*/transactions/outlets/*/orders' => Http::response(
            ['error' => 'Bad Request'], 400
        ),
    ]);

    app('cache')->store('array')->flush();
    $service = app(PaymentService::class);

    expect(fn () => $service->createPayment(
        CreateOrderData::make()->amount(Money::aed(10.00))->email('x@example.com')
    ))->toThrow(OrderCreationException::class);
});

it('throws OrderCreationException when reference is missing from the response', function () {
    Http::fake([
        '*/identity/auth/access-token' => Http::response(
            ['access_token' => 'token', 'refresh_token' => 'r'], 200
        ),
        '*/transactions/outlets/*/orders' => Http::response([
            // no 'reference' key
            'amount' => ['currencyCode' => 'AED', 'value' => 1000],
            '_embedded' => ['payment' => [['state' => 'STARTED']]],
            '_links' => [
                'payment' => ['href' => 'https://hpp.example.com/pay/x'],
            ],
        ], 201),
    ]);

    app('cache')->store('array')->flush();
    $service = app(PaymentService::class);

    expect(fn () => $service->createPayment(
        CreateOrderData::make()->amount(Money::aed(10.00))->email('x@example.com')
    ))->toThrow(OrderCreationException::class);
});

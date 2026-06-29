<?php

declare(strict_types=1);

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use mbarky\Ngenius\Http\Controllers\CallbackController;
use mbarky\Ngenius\Services\PaymentService;
use Symfony\Component\HttpKernel\Exception\HttpException;

beforeEach(fn () => app('cache')->store('array')->flush());

function fakeCallbackRetrieve(string $state = 'PURCHASED'): void
{
    Http::fake([
        '*/identity/auth/access-token' => Http::response(
            ['access_token' => 'cb-token', 'refresh_token' => 'r'],
            200
        ),
        '*/orders/*' => Http::response([
            'reference' => 'ORD-CALLBACK-001',
            'action' => 'PURCHASE',
            'amount' => ['currencyCode' => 'AED', 'value' => 10000],
            '_embedded' => ['payment' => [['state' => $state]]],
            '_links' => [
                'self' => ['href' => 'https://api-gateway.sandbox.ngenius-payments.com/transactions/outlets/test-outlet-ref/orders/ORD-CALLBACK-001'],
            ],
        ], 200),
    ]);
}

it('calls Retrieve Order Status when a ref is present — never trusts the URL alone', function () {
    fakeCallbackRetrieve('PURCHASED');

    $controller = new CallbackController(app(PaymentService::class));
    $request = Request::create('/?ref=ORD-CALLBACK-001', 'GET');
    $order = $controller($request);

    // The controller made a real Retrieve Order API call
    Http::assertSent(fn ($req) => str_contains($req->url(), '/orders/ORD-CALLBACK-001'));

    expect($order->isPaid())->toBeTrue()
        ->and($order->orderReference)->toBe('ORD-CALLBACK-001');
});

it('aborts with 400 when the ref query param is missing', function () {
    $controller = new CallbackController(app(PaymentService::class));
    $request = Request::create('/', 'GET'); // no ?ref=

    expect(fn () => $controller($request))
        ->toThrow(HttpException::class);
});

it('always verifies via API regardless of any other query params present', function () {
    fakeCallbackRetrieve('FAILED');

    $controller = new CallbackController(app(PaymentService::class));
    // Simulate a crafted URL that includes extra query params but a valid ref
    $request = Request::create('/?ref=ORD-CALLBACK-001&status=PURCHASED', 'GET');
    $order = $controller($request);

    // The real status (FAILED) is returned from the API, not the URL-provided 'status' param
    expect($order->isFailed())->toBeTrue();
});

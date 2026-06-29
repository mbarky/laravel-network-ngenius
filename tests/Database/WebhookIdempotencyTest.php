<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Http;
use mbarky\Ngenius\Contracts\NgeniusClientContract;
use mbarky\Ngenius\Contracts\PaymentRepositoryContract;
use mbarky\Ngenius\DTO\CreateOrderData;
use mbarky\Ngenius\DTO\Money;
use mbarky\Ngenius\DTO\WebhookPayload;
use mbarky\Ngenius\Events\PaymentPurchased;
use mbarky\Ngenius\Events\WebhookReceived;
use mbarky\Ngenius\Repositories\NgeniusPaymentRepository;
use mbarky\Ngenius\Services\OrderService;
use mbarky\Ngenius\Services\PaymentService;
use mbarky\Ngenius\Services\WebhookService;

beforeEach(function () {
    app()->singleton(PaymentRepositoryContract::class, NgeniusPaymentRepository::class);

    // Rebuild PaymentService and WebhookService with the live repository.
    app()->forgetInstance(PaymentService::class);
    app()->singleton(PaymentService::class, fn ($app) => new PaymentService(
        orderService: $app->make(OrderService::class),
        repository: $app->make(PaymentRepositoryContract::class),
    ));

    app()->forgetInstance(WebhookService::class);
    app()->singleton(WebhookService::class, fn ($app) => new WebhookService(
        client: $app->make(NgeniusClientContract::class),
        repository: $app->make(PaymentRepositoryContract::class),
    ));

    app('cache')->store('array')->flush();
});

// ------------------------------------------------------------------
// Helper: create the initial payment record in the DB
// ------------------------------------------------------------------

function createPersistedPayment(): void
{
    Http::fake([
        '*/identity/auth/access-token' => Http::response(
            ['access_token' => 'idem-token', 'refresh_token' => 'r'], 200
        ),
        '*/transactions/outlets/*/orders' => Http::response([
            'reference' => 'ORD-IDEM-001',
            'action' => 'PURCHASE',
            'amount' => ['currencyCode' => 'AED', 'value' => 10000],
            '_embedded' => ['payment' => [['state' => 'STARTED']]],
            '_links' => [
                'payment' => ['href' => 'https://hpp.example.com/pay/ORD-IDEM-001'],
                'self' => ['href' => 'https://example.com/orders/ORD-IDEM-001'],
            ],
        ], 201),
    ]);

    app(PaymentService::class)->createPayment(
        CreateOrderData::make()->amount(Money::aed(100.00))->email('idem@example.com')
    );

    app('cache')->store('array')->flush();
}

function fakeIdemRetrieve(string $state = 'PURCHASED'): void
{
    Http::fake([
        '*/identity/auth/access-token' => Http::response(
            ['access_token' => 'idem-token', 'refresh_token' => 'r'], 200
        ),
        '*/orders/ORD-IDEM-001' => Http::response([
            'reference' => 'ORD-IDEM-001',
            'action' => 'PURCHASE',
            'amount' => ['currencyCode' => 'AED', 'value' => 10000],
            '_embedded' => ['payment' => [['state' => $state]]],
            '_links' => ['self' => ['href' => 'https://example.com/orders/ORD-IDEM-001']],
        ], 200),
    ]);
}

// ------------------------------------------------------------------
// Tests
// ------------------------------------------------------------------

it('processes a webhook exactly once when the same eventId arrives twice', function () {
    Event::fake();
    createPersistedPayment();

    $payload = WebhookPayload::fromArray([
        'eventId' => 'EVT-IDEM-DB-001',
        'eventName' => 'PURCHASED',
        'orderReference' => 'ORD-IDEM-001',
    ]);

    // First delivery
    fakeIdemRetrieve('PURCHASED');
    app(WebhookService::class)->process($payload);

    // Second delivery — duplicate, must be silently ignored
    fakeIdemRetrieve('PURCHASED');
    app(WebhookService::class)->process($payload);

    // Event must have fired exactly once despite two deliveries
    Event::assertDispatchedTimes(PaymentPurchased::class, 1);
    Event::assertDispatchedTimes(WebhookReceived::class, 1);
});

it('processes a second webhook with a different eventId normally', function () {
    Event::fake();
    createPersistedPayment();

    fakeIdemRetrieve('PURCHASED');
    app(WebhookService::class)->process(WebhookPayload::fromArray([
        'eventId' => 'EVT-FIRST',
        'eventName' => 'PURCHASED',
        'orderReference' => 'ORD-IDEM-001',
    ]));

    app('cache')->store('array')->flush();
    fakeIdemRetrieve('PURCHASED');
    app(WebhookService::class)->process(WebhookPayload::fromArray([
        'eventId' => 'EVT-SECOND',  // different eventId
        'eventName' => 'PURCHASED',
        'orderReference' => 'ORD-IDEM-001',
    ]));

    Event::assertDispatchedTimes(PaymentPurchased::class, 2);
});

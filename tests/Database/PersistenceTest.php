<?php

declare(strict_types=1);

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Http;
use mbarky\Ngenius\Contracts\PaymentRepositoryContract;
use mbarky\Ngenius\DTO\CreateOrderData;
use mbarky\Ngenius\DTO\Money;
use mbarky\Ngenius\Models\NgeniusPayment;
use mbarky\Ngenius\Repositories\NgeniusPaymentRepository;
use mbarky\Ngenius\Services\OrderService;
use mbarky\Ngenius\Services\PaymentService;

beforeEach(function () {
    // Bind repository and rebuild PaymentService with it for every test.
    app()->singleton(PaymentRepositoryContract::class, NgeniusPaymentRepository::class);
    app()->forgetInstance(PaymentService::class);
    app()->singleton(PaymentService::class, fn ($app) => new PaymentService(
        orderService: $app->make(OrderService::class),
        repository: $app->make(PaymentRepositoryContract::class),
    ));

    app('cache')->store('array')->flush();
});

// ------------------------------------------------------------------
// Helpers
// ------------------------------------------------------------------

function fakeCreate(): void
{
    Http::fake([
        '*/identity/auth/access-token' => Http::response(
            ['access_token' => 'persist-token', 'refresh_token' => 'r'], 200
        ),
        '*/transactions/outlets/*/orders' => Http::response([
            'reference' => 'ORD-PERSIST-001',
            'action' => 'PURCHASE',
            'amount' => ['currencyCode' => 'AED', 'value' => 50000],
            '_embedded' => ['payment' => [['state' => 'STARTED']]],
            '_links' => [
                'payment' => ['href' => 'https://hpp.example.com/pay/ORD-PERSIST-001'],
                'self' => ['href' => 'https://api-gateway.sandbox.ngenius-payments.com/orders/ORD-PERSIST-001'],
            ],
        ], 201),
    ]);
}

function fakeRetrieve(string $state = 'PURCHASED'): void
{
    Http::fake([
        '*/identity/auth/access-token' => Http::response(
            ['access_token' => 'persist-token', 'refresh_token' => 'r'], 200
        ),
        '*/orders/ORD-PERSIST-001' => Http::response([
            'reference' => 'ORD-PERSIST-001',
            'action' => 'PURCHASE',
            'amount' => ['currencyCode' => 'AED', 'value' => 50000],
            '_embedded' => ['payment' => [['state' => $state]]],
            '_links' => ['self' => ['href' => 'https://example.com/orders/ORD-PERSIST-001']],
        ], 200),
    ]);
}

// ------------------------------------------------------------------
// Tests
// ------------------------------------------------------------------

it('persists a payment record when persist_transactions = true', function () {
    fakeCreate();

    app(PaymentService::class)->createPayment(
        CreateOrderData::make()
            ->amount(Money::aed(500.00))
            ->email('persist@example.com')
            ->merchantOrderReference('INV-PERSIST')
    );

    expect(NgeniusPayment::count())->toBe(1);

    $record = NgeniusPayment::first();
    expect($record->ngenius_order_reference)->toBe('ORD-PERSIST-001')
        ->and($record->merchant_order_reference)->toBe('INV-PERSIST')
        ->and($record->currency)->toBe('AED')
        ->and($record->amount_minor)->toBe(50000)
        ->and($record->status)->toBe('STARTED')
        ->and($record->payment_url)->toBe('https://hpp.example.com/pay/ORD-PERSIST-001');
});

it('updates status to PURCHASED and sets paid_at when retrieved as paid', function () {
    fakeCreate();
    app(PaymentService::class)->createPayment(
        CreateOrderData::make()->amount(Money::aed(500.00))->email('x@example.com')
    );
    app('cache')->store('array')->flush();

    fakeRetrieve('PURCHASED');
    app(PaymentService::class)->retrieveOrder('ORD-PERSIST-001');

    $record = NgeniusPayment::first();
    expect($record->status)->toBe('PURCHASED')
        ->and($record->paid_at)->not->toBeNull();
});

it('sets failed_at when order is retrieved as FAILED', function () {
    fakeCreate();
    app(PaymentService::class)->createPayment(
        CreateOrderData::make()->amount(Money::aed(500.00))->email('x@example.com')
    );
    app('cache')->store('array')->flush();

    fakeRetrieve('FAILED');
    app(PaymentService::class)->retrieveOrder('ORD-PERSIST-001');

    $record = NgeniusPayment::first();
    expect($record->status)->toBe('FAILED')
        ->and($record->failed_at)->not->toBeNull()
        ->and($record->paid_at)->toBeNull();
});

it('stores the polymorphic payable association', function () {
    $payable = new class extends Model
    {
        protected $table = 'fake_invoices';

        public $incrementing = false;

        protected $primaryKey = 'id';

        public function getKey(): mixed
        {
            return 99;
        }

        public function getMorphClass(): string
        {
            return 'invoice';
        }
    };

    fakeCreate();
    app(PaymentService::class)->createPayment(
        CreateOrderData::make()
            ->amount(Money::aed(500.00))
            ->email('x@example.com')
            ->for($payable)
    );

    $record = NgeniusPayment::first();
    expect($record->payable_type)->toBe('invoice')
        ->and((int) $record->payable_id)->toBe(99);
});

it('findByOrderReference returns null when no record exists', function () {
    expect(app(PaymentRepositoryContract::class)->findByOrderReference('NON-EXISTENT'))
        ->toBeNull();
});

it('does not persist when persist_transactions = false', function () {
    app()->forgetInstance(PaymentService::class);
    app()->singleton(PaymentService::class, fn ($app) => new PaymentService(
        orderService: $app->make(OrderService::class),
        repository: null,
    ));

    fakeCreate();
    app(PaymentService::class)->createPayment(
        CreateOrderData::make()->amount(Money::aed(100.00))->email('x@example.com')
    );

    expect(NgeniusPayment::count())->toBe(0);
});

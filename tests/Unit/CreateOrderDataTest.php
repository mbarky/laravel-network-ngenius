<?php

declare(strict_types=1);

use Illuminate\Database\Eloquent\Model;
use mbarky\Ngenius\DTO\CreateOrderData;
use mbarky\Ngenius\DTO\Money;
use mbarky\Ngenius\Enums\PaymentAction;

it('builds a CreateOrderData with fluent setters (immutable clone)', function () {
    $data = CreateOrderData::make()
        ->amount(Money::aed(100.00))
        ->email('buyer@example.com')
        ->merchantOrderReference('INV-001')
        ->redirectUrl('https://example.com/callback');

    expect($data->getAmount()->minorUnits)->toBe(10000)
        ->and($data->getEmail())->toBe('buyer@example.com')
        ->and($data->getMerchantOrderReference())->toBe('INV-001')
        ->and($data->getRedirectUrl())->toBe('https://example.com/callback');
});

it('defaults the action to the configured value', function () {
    $data = CreateOrderData::make()
        ->amount(Money::aed(50.00))
        ->email('x@example.com');

    // Config sets action = PURCHASE in TestCase::defineEnvironment
    expect($data->getAction())->toBe(PaymentAction::Purchase);
});

it('overrides the action via ->action()', function () {
    $data = CreateOrderData::make()
        ->amount(Money::aed(50.00))
        ->email('x@example.com')
        ->action(PaymentAction::Auth);

    expect($data->getAction())->toBe(PaymentAction::Auth);
});

it('each fluent setter returns a new immutable instance', function () {
    $original = CreateOrderData::make()
        ->amount(Money::aed(10.00))
        ->email('original@example.com');

    $modified = $original->email('modified@example.com');

    expect($original->getEmail())->toBe('original@example.com')
        ->and($modified->getEmail())->toBe('modified@example.com')
        ->and($original)->not->toBe($modified);
});

it('falls back to config redirect_url when none is set', function () {
    config(['ngenius.redirect_url' => 'https://example.com/default-callback']);

    $data = CreateOrderData::make()
        ->amount(Money::aed(10.00))
        ->email('x@example.com');

    expect($data->getRedirectUrl())->toBe('https://example.com/default-callback');
});

it('attaches a polymorphic payable model via ->for()', function () {
    $model = new class extends Model
    {
        protected $table = 'invoices';

        public $incrementing = false;

        protected $primaryKey = 'id';

        public function getKey(): mixed
        {
            return 42;
        }

        public function getMorphClass(): string
        {
            return 'invoice';
        }
    };

    $data = CreateOrderData::make()
        ->amount(Money::aed(10.00))
        ->email('x@example.com')
        ->for($model);

    expect($data->getPayable())->toBe($model);
});

<?php

declare(strict_types=1);

namespace mbarky\Ngenius\DTO;

use Illuminate\Database\Eloquent\Model;
use mbarky\Ngenius\Enums\PaymentAction;

/**
 * Fluent builder for HPP order creation data.
 *
 * Usage:
 *   CreateOrderData::make()
 *       ->amount(Money::aed(250.00))
 *       ->email('customer@example.com')
 *       ->merchantOrderReference('INV-10025')
 *       ->for($invoice)
 *       ->redirectUrl(route('payment.callback'))
 */
final class CreateOrderData
{
    private Money $amount;

    private string $email;

    private ?string $merchantOrderReference = null;

    private ?string $redirectUrl = null;

    private PaymentAction $action;

    private ?Model $payable = null;

    private ?BillingAddress $billingAddress = null;

    private ?string $language = null;

    private function __construct()
    {
        $this->action = PaymentAction::default();
    }

    public static function make(): self
    {
        return new self;
    }

    public function amount(Money $money): self
    {
        $clone = clone $this;
        $clone->amount = $money;

        return $clone;
    }

    public function email(string $email): self
    {
        $clone = clone $this;
        $clone->email = $email;

        return $clone;
    }

    public function merchantOrderReference(string $ref): self
    {
        $clone = clone $this;
        $clone->merchantOrderReference = $ref;

        return $clone;
    }

    public function redirectUrl(string $url): self
    {
        $clone = clone $this;
        $clone->redirectUrl = $url;

        return $clone;
    }

    public function action(PaymentAction $action): self
    {
        $clone = clone $this;
        $clone->action = $action;

        return $clone;
    }

    /** Polymorphic association — attach this payment to any Eloquent model. */
    public function for(Model $model): self
    {
        $clone = clone $this;
        $clone->payable = $model;

        return $clone;
    }

    public function billingAddress(BillingAddress $address): self
    {
        $clone = clone $this;
        $clone->billingAddress = $address;

        return $clone;
    }

    public function language(string $language): self
    {
        $clone = clone $this;
        $clone->language = $language;

        return $clone;
    }

    // -----------------------------------------------------------------
    // Accessors (used by services)
    // -----------------------------------------------------------------

    public function getAmount(): Money
    {
        return $this->amount;
    }

    public function getEmail(): string
    {
        return $this->email;
    }

    public function getMerchantOrderReference(): ?string
    {
        return $this->merchantOrderReference;
    }

    public function getRedirectUrl(): ?string
    {
        if ($this->redirectUrl !== null) {
            return $this->redirectUrl;
        }

        $raw = config('ngenius.redirect_url');

        return is_string($raw) ? $raw : null;
    }

    public function getAction(): PaymentAction
    {
        return $this->action;
    }

    public function getPayable(): ?Model
    {
        return $this->payable;
    }

    public function getBillingAddress(): ?BillingAddress
    {
        return $this->billingAddress;
    }

    public function getLanguage(): string
    {
        if ($this->language !== null) {
            return $this->language;
        }

        $raw = config('ngenius.language', 'en');

        return is_string($raw) ? $raw : 'en';
    }
}

<?php

declare(strict_types=1);

namespace mbarky\Ngenius\Services;

use mbarky\Ngenius\Contracts\NgeniusClientContract;
use mbarky\Ngenius\DTO\CreateOrderData;
use mbarky\Ngenius\DTO\Money;
use mbarky\Ngenius\DTO\NgeniusOrder;
use mbarky\Ngenius\Enums\PaymentState;
use mbarky\Ngenius\Exceptions\OrderCreationException;

final class OrderService
{
    public function __construct(
        private readonly NgeniusClientContract $client,
    ) {}

    /** @throws OrderCreationException */
    public function create(CreateOrderData $data): NgeniusOrder
    {
        $response = $this->client->createOrder($data);

        // Extract the HPP redirect URL from _links.payment.href (HAL).
        $paymentUrl = $response->link('payment');

        $rawRef = $response->get('reference');
        if (! is_string($rawRef) || $rawRef === '') {
            throw new OrderCreationException('N-Genius response missing order reference.');
        }

        $rawState = $response->get('_embedded.payment.0.state');
        $state = PaymentState::from(is_string($rawState) ? $rawState : 'STARTED');

        $rawValue = $response->get('amount.value', 0);
        $rawCurrency = $response->get('amount.currencyCode');
        $amount = Money::fromMinorUnits(
            is_int($rawValue) ? $rawValue : 0,
            is_string($rawCurrency) ? $rawCurrency : $data->getAmount()->currency,
        );

        return new NgeniusOrder(
            orderReference: $rawRef,
            paymentUrl: $paymentUrl,
            amount: $amount,
            state: $state,
            raw: $response->body(),
        );
    }

    public function retrieve(string $orderReference): NgeniusOrder
    {
        $response = $this->client->retrieveOrder($orderReference);

        $rawState = $response->get('_embedded.payment.0.state');
        $state = PaymentState::from(is_string($rawState) ? $rawState : 'STARTED');

        $rawValue = $response->get('amount.value', 0);
        $rawCurrency = $response->get('amount.currencyCode');
        $rawDefault = config('ngenius.currency', 'AED');
        $amount = Money::fromMinorUnits(
            is_int($rawValue) ? $rawValue : 0,
            is_string($rawCurrency) ? $rawCurrency : (is_string($rawDefault) ? $rawDefault : 'AED'),
        );

        $paymentUrl = $response->linkOrNull('payment') ?? '';

        $rawRef = $response->get('reference');

        return new NgeniusOrder(
            orderReference: is_string($rawRef) ? $rawRef : $orderReference,
            paymentUrl: $paymentUrl,
            amount: $amount,
            state: $state,
            raw: $response->body(),
        );
    }
}

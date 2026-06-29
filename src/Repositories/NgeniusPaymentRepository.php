<?php

declare(strict_types=1);

namespace mbarky\Ngenius\Repositories;

use mbarky\Ngenius\Contracts\PaymentRepositoryContract;
use mbarky\Ngenius\DTO\CreateOrderData;
use mbarky\Ngenius\DTO\NgeniusOrder;
use mbarky\Ngenius\Enums\Environment;
use mbarky\Ngenius\Enums\PaymentState;
use mbarky\Ngenius\Models\NgeniusPayment;

final class NgeniusPaymentRepository implements PaymentRepositoryContract
{
    public function create(CreateOrderData $data, NgeniusOrder $order): NgeniusPayment
    {
        $record = new NgeniusPayment;

        if ($payable = $data->getPayable()) {
            $record->payable_type = $payable->getMorphClass();
            $key = $payable->getKey();
            $record->payable_id = is_int($key) || is_string($key) ? $key : null;
        }

        $record->merchant_order_reference = $data->getMerchantOrderReference();
        $record->ngenius_order_reference = $order->orderReference;
        $record->outlet_reference = Environment::fromConfig()->outletReference();
        $record->action = $data->getAction()->value;
        $record->currency = $order->amount->currency;
        $record->amount_minor = $order->amount->minorUnits;
        $record->status = $order->state->value;
        $record->payment_url = $order->paymentUrl;
        $record->raw_order_response = $order->raw;

        $record->save();

        return $record;
    }

    public function findByOrderReference(string $orderReference): ?NgeniusPayment
    {
        return NgeniusPayment::where('ngenius_order_reference', $orderReference)->first();
    }

    public function updateFromOrder(string $orderReference, NgeniusOrder $order): ?NgeniusPayment
    {
        $record = $this->findByOrderReference($orderReference);

        if ($record === null) {
            return null;
        }

        $record->status = $order->state->value;
        $record->raw_status_response = $order->raw;

        if ($order->state === PaymentState::Purchased || $order->state === PaymentState::Captured) {
            $record->paid_at ??= now();
        }

        if ($order->state->isFailed()) {
            $record->failed_at ??= now();
        }

        $record->save();

        return $record;
    }

    public function isWebhookEventProcessed(string $eventId): bool
    {
        return NgeniusPayment::where('last_webhook_event_id', $eventId)->exists();
    }

    /** @param array<mixed, mixed> $payload */
    public function recordWebhookEvent(
        string $orderReference,
        string $eventId,
        string $event,
        array $payload,
    ): void {
        NgeniusPayment::where('ngenius_order_reference', $orderReference)
            ->update([
                'last_webhook_event' => $event,
                'last_webhook_event_id' => $eventId,
                'last_webhook_payload' => $payload,
            ]);
    }
}

<?php

declare(strict_types=1);

namespace mbarky\Ngenius\Contracts;

use mbarky\Ngenius\DTO\CreateOrderData;
use mbarky\Ngenius\DTO\NgeniusOrder;
use mbarky\Ngenius\Models\NgeniusPayment;

interface PaymentRepositoryContract
{
    public function create(CreateOrderData $data, NgeniusOrder $order): NgeniusPayment;

    public function findByOrderReference(string $orderReference): ?NgeniusPayment;

    public function updateFromOrder(string $orderReference, NgeniusOrder $order): ?NgeniusPayment;

    public function isWebhookEventProcessed(string $eventId): bool;

    /** @param array<mixed, mixed> $payload */
    public function recordWebhookEvent(string $orderReference, string $eventId, string $event, array $payload): void;
}

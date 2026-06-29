<?php

declare(strict_types=1);

namespace mbarky\Ngenius\Services;

use mbarky\Ngenius\Contracts\PaymentRepositoryContract;
use mbarky\Ngenius\DTO\CreateOrderData;
use mbarky\Ngenius\DTO\NgeniusOrder;

/**
 * Primary facade target — orchestrates order creation and optional persistence.
 */
final class PaymentService
{
    public function __construct(
        private readonly OrderService $orderService,
        private readonly ?PaymentRepositoryContract $repository,
    ) {}

    public function createPayment(CreateOrderData $data): NgeniusOrder
    {
        $order = $this->orderService->create($data);

        if ($this->repository !== null) {
            $this->repository->create($data, $order);
        }

        return $order;
    }

    public function retrieveOrder(string $orderReference): NgeniusOrder
    {
        $order = $this->orderService->retrieve($orderReference);

        if ($this->repository !== null) {
            $this->repository->updateFromOrder($orderReference, $order);
        }

        return $order;
    }
}

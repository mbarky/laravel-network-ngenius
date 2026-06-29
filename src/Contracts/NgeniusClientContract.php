<?php

declare(strict_types=1);

namespace mbarky\Ngenius\Contracts;

use mbarky\Ngenius\Client\NgeniusResponse;
use mbarky\Ngenius\DTO\CreateOrderData;

interface NgeniusClientContract
{
    public function createOrder(CreateOrderData $data): NgeniusResponse;

    public function retrieveOrder(string $orderReference): NgeniusResponse;
}

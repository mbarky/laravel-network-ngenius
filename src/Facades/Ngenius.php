<?php

declare(strict_types=1);

namespace mbarky\Ngenius\Facades;

use Illuminate\Support\Facades\Facade;
use mbarky\Ngenius\DTO\CreateOrderData;
use mbarky\Ngenius\DTO\NgeniusOrder;
use mbarky\Ngenius\Services\PaymentService;

/**
 * @method static NgeniusOrder createPayment(CreateOrderData $data)
 * @method static NgeniusOrder retrieveOrder(string $orderReference)
 *
 * @see PaymentService
 */
class Ngenius extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return PaymentService::class;
    }
}

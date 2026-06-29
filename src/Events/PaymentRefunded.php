<?php

declare(strict_types=1);

namespace mbarky\Ngenius\Events;

use mbarky\Ngenius\DTO\NgeniusOrder;
use mbarky\Ngenius\DTO\WebhookPayload;

/** Dispatched on REFUNDED or PARTIALLY_REFUNDED webhook events. v1.1 */
final class PaymentRefunded
{
    public function __construct(
        public readonly NgeniusOrder $order,
        public readonly WebhookPayload $webhookPayload,
    ) {}
}

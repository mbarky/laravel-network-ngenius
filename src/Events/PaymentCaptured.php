<?php

declare(strict_types=1);

namespace mbarky\Ngenius\Events;

use mbarky\Ngenius\DTO\NgeniusOrder;
use mbarky\Ngenius\DTO\WebhookPayload;

final class PaymentCaptured
{
    public function __construct(
        public readonly NgeniusOrder $order,
        public readonly WebhookPayload $webhookPayload,
    ) {}
}

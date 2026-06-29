<?php

declare(strict_types=1);

namespace mbarky\Ngenius\Events;

use mbarky\Ngenius\DTO\NgeniusOrder;
use mbarky\Ngenius\DTO\WebhookPayload;

/** Fired for every verified, non-duplicate webhook delivery. */
final class WebhookReceived
{
    public function __construct(
        public readonly WebhookPayload $payload,
        public readonly NgeniusOrder $order,
    ) {}
}

<?php

declare(strict_types=1);

namespace mbarky\Ngenius\Enums;

enum WebhookEvent: string
{
    case Authorised = 'AUTHORISED';
    case Captured = 'CAPTURED';
    case Purchased = 'PURCHASED';
    case Failed = 'FAILED';
    case Declined = 'DECLINED';
    case Cancelled = 'CANCELLED';
    case Refunded = 'REFUNDED';            // v1.1 — Refund flow
    case PartiallyRefunded = 'PARTIALLY_REFUNDED';  // v1.1 — Refund flow
}

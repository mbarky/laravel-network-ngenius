<?php

declare(strict_types=1);

namespace mbarky\Ngenius\Enums;

enum PaymentState: string
{
    case Started = 'STARTED';
    case Await3ds = 'AWAIT_3DS';
    case Authorised = 'AUTHORISED';
    case Captured = 'CAPTURED';
    case Purchased = 'PURCHASED';
    case PartiallyCaptured = 'PARTIALLY_CAPTURED';
    case Refunded = 'REFUNDED';              // v1.1 — Refund flow
    case PartiallyRefunded = 'PARTIALLY_REFUNDED';    // v1.1 — Refund flow
    case Failed = 'FAILED';
    case Declined = 'DECLINED';
    case Cancelled = 'CANCELLED';
    case OrderClosed = 'ORDER_CLOSED';

    /** Returns true for any terminal "paid" state (HPP PURCHASE / SALE). */
    public function isPaid(): bool
    {
        return match ($this) {
            self::Purchased, self::Captured, self::Authorised => true,
            default => false,
        };
    }

    public function isFailed(): bool
    {
        return match ($this) {
            self::Failed, self::Declined, self::Cancelled, self::OrderClosed => true,
            default => false,
        };
    }

    public function isTerminal(): bool
    {
        return $this->isPaid() || $this->isFailed()
            || $this === self::Refunded
            || $this === self::PartiallyRefunded;
    }
}

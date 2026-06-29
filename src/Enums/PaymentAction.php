<?php

declare(strict_types=1);

namespace mbarky\Ngenius\Enums;

enum PaymentAction: string
{
    case Purchase = 'PURCHASE';
    case Auth = 'AUTH';     // v1.2 — AUTH + Capture flow
    case Sale = 'SALE';

    public static function default(): self
    {
        $raw = config('ngenius.action', 'PURCHASE');

        return self::from(strtoupper(is_string($raw) ? $raw : 'PURCHASE'));
    }
}

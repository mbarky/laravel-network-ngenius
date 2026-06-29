<?php

declare(strict_types=1);

namespace mbarky\Ngenius\DTO;

use mbarky\Ngenius\Enums\PaymentState;

/**
 * Immutable representation of an N-Genius order returned by the API.
 */
final readonly class NgeniusOrder
{
    public function __construct(
        public readonly string $orderReference,
        public readonly string $paymentUrl,
        public readonly Money $amount,
        public readonly PaymentState $state,
        /** @phpstan-var array<mixed, mixed> */
        public readonly array $raw,
    ) {}

    public function paymentUrl(): string
    {
        return $this->paymentUrl;
    }

    public function isPaid(): bool
    {
        return $this->state->isPaid();
    }

    public function isFailed(): bool
    {
        return $this->state->isFailed();
    }

    public function isTerminal(): bool
    {
        return $this->state->isTerminal();
    }
}

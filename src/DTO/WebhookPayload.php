<?php

declare(strict_types=1);

namespace mbarky\Ngenius\DTO;

use mbarky\Ngenius\Exceptions\InvalidWebhookException;

final readonly class WebhookPayload
{
    /**
     * @param  array<mixed, mixed>  $raw
     */
    public function __construct(
        public readonly string $eventId,
        public readonly string $eventName,
        public readonly string $orderReference,
        public readonly ?string $outletReference,
        public readonly array $raw,
    ) {}

    /**
     * @param  array<mixed, mixed>  $data
     *
     * @throws InvalidWebhookException
     */
    public static function fromArray(array $data): self
    {
        $rawEventId = data_get($data, 'eventId');
        if (! is_string($rawEventId) || $rawEventId === '') {
            throw new InvalidWebhookException('Webhook payload missing eventId.');
        }

        $rawEventName = data_get($data, 'eventName');
        if (! is_string($rawEventName) || $rawEventName === '') {
            throw new InvalidWebhookException('Webhook payload missing eventName.');
        }

        $rawOrderRef = data_get($data, 'orderReference');
        if (! is_string($rawOrderRef) || $rawOrderRef === '') {
            throw new InvalidWebhookException('Webhook payload missing orderReference.');
        }

        $rawOutletRef = data_get($data, 'outletReference');

        return new self(
            eventId: $rawEventId,
            eventName: $rawEventName,
            orderReference: $rawOrderRef,
            outletReference: is_string($rawOutletRef) && $rawOutletRef !== '' ? $rawOutletRef : null,
            raw: $data,
        );
    }
}

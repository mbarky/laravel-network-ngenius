<?php

declare(strict_types=1);

namespace mbarky\Ngenius\Services;

use Illuminate\Support\Facades\Event;
use mbarky\Ngenius\Contracts\NgeniusClientContract;
use mbarky\Ngenius\Contracts\PaymentRepositoryContract;
use mbarky\Ngenius\DTO\WebhookPayload;
use mbarky\Ngenius\Enums\WebhookEvent;
use mbarky\Ngenius\Events\PaymentAuthorised;
use mbarky\Ngenius\Events\PaymentCaptured;
use mbarky\Ngenius\Events\PaymentFailed;
use mbarky\Ngenius\Events\PaymentPurchased;
use mbarky\Ngenius\Events\PaymentRefunded;
use mbarky\Ngenius\Events\WebhookReceived;

final class WebhookService
{
    public function __construct(
        private readonly NgeniusClientContract $client,
        private readonly ?PaymentRepositoryContract $repository,
    ) {}

    /**
     * Process a verified webhook payload idempotently.
     *
     * Idempotency is keyed on eventId — duplicate deliveries are silently skipped.
     */
    public function process(WebhookPayload $payload): void
    {
        // Idempotency check — skip already-processed events.
        if ($this->repository?->isWebhookEventProcessed($payload->eventId)) {
            return;
        }

        // Always reconcile via Retrieve Order Status (never trust the webhook payload alone).
        $order = (new OrderService($this->client))->retrieve($payload->orderReference);

        // Persist state if repository is available.
        if ($this->repository !== null) {
            $this->repository->recordWebhookEvent(
                orderReference: $payload->orderReference,
                eventId: $payload->eventId,
                event: $payload->eventName,
                payload: $payload->raw,
            );
            $this->repository->updateFromOrder($payload->orderReference, $order);
        }

        // Dispatch domain events.
        Event::dispatch(new WebhookReceived($payload, $order));

        $event = WebhookEvent::tryFrom(strtoupper($payload->eventName));

        match ($event) {
            WebhookEvent::Authorised => Event::dispatch(new PaymentAuthorised($order, $payload)),
            WebhookEvent::Purchased => Event::dispatch(new PaymentPurchased($order, $payload)),
            WebhookEvent::Captured => Event::dispatch(new PaymentCaptured($order, $payload)),
            WebhookEvent::Failed,
            WebhookEvent::Declined => Event::dispatch(new PaymentFailed($order, $payload)),
            WebhookEvent::Refunded,
            WebhookEvent::PartiallyRefunded => Event::dispatch(new PaymentRefunded($order, $payload)), // v1.1
            default => null,
        };
    }
}

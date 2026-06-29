<?php

declare(strict_types=1);

namespace mbarky\Ngenius\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use mbarky\Ngenius\DTO\WebhookPayload;
use mbarky\Ngenius\Services\WebhookService;

final class ProcessWebhookJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public function __construct(
        private readonly WebhookPayload $payload,
    ) {}

    public function handle(WebhookService $service): void
    {
        $service->process($this->payload);
    }
}

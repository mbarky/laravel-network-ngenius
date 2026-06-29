<?php

declare(strict_types=1);

namespace mbarky\Ngenius\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Queue;
use mbarky\Ngenius\DTO\WebhookPayload;
use mbarky\Ngenius\Exceptions\InvalidWebhookException;
use mbarky\Ngenius\Jobs\ProcessWebhookJob;

/**
 * Receives N-Genius webhook POSTs.
 *
 * N-Genius sends ONE POST per event with NO retries — a missed event is lost.
 * Strategy: verify secret header → acknowledge 200 immediately → queue processing.
 * Heavy work (Retrieve Order Status, DB writes, event dispatch) runs in the queue.
 */
class WebhookController extends Controller
{
    public function __invoke(Request $request): JsonResponse
    {
        // Middleware VerifyNgeniusWebhook has already run; we are authenticated here.

        /** @var array<string, mixed> $data */
        $data = $request->json()->all();

        try {
            $payload = WebhookPayload::fromArray($data);
        } catch (InvalidWebhookException $e) {
            if (config('ngenius.logging.enabled')) {
                $rawChannel = config('ngenius.logging.channel', 'stack');
                Log::channel(is_string($rawChannel) ? $rawChannel : 'stack')
                    ->warning('N-Genius webhook malformed payload', ['error' => $e->getMessage()]);
            }

            return response()->json(['error' => $e->getMessage()], 422);
        }

        // Dispatch to queue — must respond within 15 s.
        $rawQueue = config('ngenius.webhook.queue', 'default');
        Queue::connection(null)->pushOn(
            is_string($rawQueue) ? $rawQueue : 'default',
            new ProcessWebhookJob($payload)
        );

        return response()->json(['received' => true], 200);
    }
}

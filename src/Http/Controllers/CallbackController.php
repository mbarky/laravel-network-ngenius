<?php

declare(strict_types=1);

namespace mbarky\Ngenius\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use mbarky\Ngenius\Services\PaymentService;

/**
 * Handles the browser return from the N-Genius HPP.
 *
 * N-Genius appends ?ref={orderReference} to the configured redirectUrl.
 * IMPORTANT: the ref parameter must NEVER be trusted alone.
 * Status is always verified via Retrieve Order Status.
 */
class CallbackController extends Controller
{
    public function __construct(
        private readonly PaymentService $paymentService,
    ) {}

    public function __invoke(Request $request): mixed
    {
        $ref = (string) $request->query('ref', '');

        if ($ref === '') {
            abort(400, 'Missing payment reference.');
        }

        // Always verify via the API — never trust the return URL alone.
        $order = $this->paymentService->retrieveOrder($ref);

        return $order;
    }
}

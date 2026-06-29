<?php

declare(strict_types=1);

namespace mbarky\Ngenius\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Verifies N-Genius webhook origin via the configured secret header.
 *
 * Payloads from N-Genius are NOT signed. Origin is verified by checking that
 * a custom header matches a shared secret configured in the portal and in
 * config/ngenius.php (webhook.header_name / webhook.header_value).
 */
final class VerifyNgeniusWebhook
{
    /**
     * @param  Closure(Request): Response  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $rawName = config('ngenius.webhook.header_name');
        $rawValue = config('ngenius.webhook.header_value');
        $headerName = is_string($rawName) ? $rawName : '';
        $headerValue = is_string($rawValue) ? $rawValue : '';

        if ($headerName === '' || $headerValue === '') {
            abort(500, 'N-Genius webhook secret header not configured.');
        }

        $incoming = $request->header($headerName);
        if (! hash_equals($headerValue, is_string($incoming) ? $incoming : '')) {
            abort(401, 'Invalid N-Genius webhook signature.');
        }

        return $next($request);
    }
}

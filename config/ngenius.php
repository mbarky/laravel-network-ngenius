<?php

declare(strict_types=1);

return [
    /*
    |--------------------------------------------------------------------------
    | Environment
    |--------------------------------------------------------------------------
    | Determines which credential block is used. Values: 'sandbox', 'production'
    */
    'environment' => env('NGENIUS_ENV', 'sandbox'),

    /*
    |--------------------------------------------------------------------------
    | Sandbox Credentials
    |--------------------------------------------------------------------------
    */
    'sandbox' => [
        'base_url' => 'https://api-gateway.sandbox.ngenius-payments.com',
        'api_key' => env('NGENIUS_SANDBOX_API_KEY'),
        'outlet_reference' => env('NGENIUS_SANDBOX_OUTLET_REF'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Production Credentials
    |--------------------------------------------------------------------------
    */
    'production' => [
        'base_url' => 'https://api-gateway.ngenius-payments.com',
        'api_key' => env('NGENIUS_PRODUCTION_API_KEY'),
        'outlet_reference' => env('NGENIUS_PRODUCTION_OUTLET_REF'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Payment Defaults
    |--------------------------------------------------------------------------
    */
    'currency' => env('NGENIUS_CURRENCY', 'AED'),
    'action' => env('NGENIUS_ACTION', 'PURCHASE'),   // PURCHASE | AUTH | SALE
    'language' => env('NGENIUS_LANGUAGE', 'en'),
    'redirect_url' => env('NGENIUS_REDIRECT_URL'),

    /*
    |--------------------------------------------------------------------------
    | Webhook
    |--------------------------------------------------------------------------
    | N-Genius sends ONE POST per event with NO retries. Verify origin via a
    | secret header (configure the same name + value in the N-Genius portal).
    */
    'webhook' => [
        'enabled' => env('NGENIUS_WEBHOOK_ENABLED', true),
        'route' => env('NGENIUS_WEBHOOK_ROUTE', 'ngenius/webhook'),
        'header_name' => env('NGENIUS_WEBHOOK_HEADER_NAME', 'X-Ngenius-Hmac-Sha256'),
        'header_value' => env('NGENIUS_WEBHOOK_HEADER_VALUE'),
        'queue' => env('NGENIUS_WEBHOOK_QUEUE', 'default'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Token Cache
    |--------------------------------------------------------------------------
    | Access tokens are valid for 300 s; we cache for 270 s (safety margin).
    */
    'cache' => [
        'store' => env('NGENIUS_CACHE_STORE', 'file'),
        'token_key' => env('NGENIUS_CACHE_TOKEN_KEY', 'ngenius_access_token'),
        'ttl_seconds' => (int) env('NGENIUS_CACHE_TTL', 270),
    ],

    /*
    |--------------------------------------------------------------------------
    | Logging
    |--------------------------------------------------------------------------
    */
    'logging' => [
        'enabled' => env('NGENIUS_LOG_ENABLED', true),
        'channel' => env('NGENIUS_LOG_CHANNEL', 'stack'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Persistence
    |--------------------------------------------------------------------------
    | When true, every payment and its lifecycle are stored in ngenius_payments.
    */
    'persist_transactions' => env('NGENIUS_PERSIST', true),
];

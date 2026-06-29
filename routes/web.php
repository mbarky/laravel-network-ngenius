<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use mbarky\Ngenius\Http\Controllers\WebhookController;
use mbarky\Ngenius\Http\Middleware\VerifyNgeniusWebhook;

/*
|--------------------------------------------------------------------------
| N-Genius Webhook Route
|--------------------------------------------------------------------------
| Registered automatically when webhook.enabled = true.
| The route prefix is configurable via webhook.route in config/ngenius.php.
*/

Route::post(
    config('ngenius.webhook.route', 'ngenius/webhook'),
    WebhookController::class,
)
    ->middleware(VerifyNgeniusWebhook::class)
    ->name('ngenius.webhook');

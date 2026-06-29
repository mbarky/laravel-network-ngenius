# laravel-network-ngenius

A production-grade Laravel package for the **N-Genius Online payment gateway** by Network International.
Covers the complete **Hosted Payment Page (HPP)** flow for v1.0.

[![Tests](https://github.com/mbarky/laravel-network-ngenius/actions/workflows/tests.yml/badge.svg)](https://github.com/mbarky/laravel-network-ngenius/actions)
[![PHP](https://img.shields.io/badge/PHP-8.2%2B-blue)](https://www.php.net/)
[![Laravel](https://img.shields.io/badge/Laravel-10%20|%2011%20|%2012-red)](https://laravel.com/)
[![License](https://img.shields.io/badge/license-MIT-green)](LICENSE.md)

---

## Contents

1. [Requirements](#requirements)
2. [Installation](#installation)
3. [Configuration](#configuration)
4. [Environment Variables](#environment-variables)
5. [Usage — Create a Payment](#usage--create-a-payment)
6. [Usage — Callback / Return URL](#usage--callback--return-url)
7. [Usage — Retrieve Order Status](#usage--retrieve-order-status)
8. [Webhooks](#webhooks)
9. [Events](#events)
10. [Persistence](#persistence)
11. [Artisan Health Check](#artisan-health-check)
12. [Testing your application](#testing-your-application)
13. [Security Notes](#security-notes)
14. [Roadmap](#roadmap)

---

## Requirements

- PHP `^8.2`
- Laravel `^10.0 || ^11.0 || ^12.0`
- An N-Genius sandbox or production account (API key + outlet reference)

---

## Installation

```bash
composer require mbarky/laravel-network-ngenius
```

The service provider and `Ngenius` Facade are auto-discovered via Composer's `extra.laravel` block.

### Publish config

```bash
php artisan vendor:publish --tag=ngenius-config
```

### Publish and run the migration (optional — required only if `persist_transactions = true`)

```bash
php artisan vendor:publish --tag=ngenius-migrations
php artisan migrate
```

---

## Configuration

After publishing, edit `config/ngenius.php`. All secrets must come from environment variables — never hardcode credentials.

```php
// config/ngenius.php (excerpt)
'environment' => env('NGENIUS_ENV', 'sandbox'),   // 'sandbox' or 'production'

'sandbox' => [
    'base_url'         => 'https://api-gateway.sandbox.ngenius-payments.com',
    'api_key'          => env('NGENIUS_SANDBOX_API_KEY'),
    'outlet_reference' => env('NGENIUS_SANDBOX_OUTLET_REF'),
],

'production' => [
    'base_url'         => 'https://api-gateway.ngenius-payments.com',
    'api_key'          => env('NGENIUS_PRODUCTION_API_KEY'),
    'outlet_reference' => env('NGENIUS_PRODUCTION_OUTLET_REF'),
],

'currency'     => env('NGENIUS_CURRENCY', 'AED'),
'action'       => env('NGENIUS_ACTION', 'PURCHASE'),  // PURCHASE | AUTH | SALE
'redirect_url' => env('NGENIUS_REDIRECT_URL'),

'webhook' => [
    'enabled'      => env('NGENIUS_WEBHOOK_ENABLED', true),
    'route'        => env('NGENIUS_WEBHOOK_ROUTE', 'ngenius/webhook'),
    'header_name'  => env('NGENIUS_WEBHOOK_HEADER_NAME', 'X-Ngenius-Hmac-Sha256'),
    'header_value' => env('NGENIUS_WEBHOOK_HEADER_VALUE'),
    'queue'        => env('NGENIUS_WEBHOOK_QUEUE', 'default'),
],

'persist_transactions' => env('NGENIUS_PERSIST', true),
```

---

## Environment Variables

```dotenv
# Required
NGENIUS_ENV=sandbox
NGENIUS_SANDBOX_API_KEY=your-sandbox-api-key
NGENIUS_SANDBOX_OUTLET_REF=your-sandbox-outlet-ref
NGENIUS_REDIRECT_URL=https://yourapp.com/payment/callback

# Webhook (configure the same header name+value in the N-Genius portal)
NGENIUS_WEBHOOK_HEADER_NAME=X-Ngenius-Hmac-Sha256
NGENIUS_WEBHOOK_HEADER_VALUE=your-shared-webhook-secret

# Optional
NGENIUS_CURRENCY=AED
NGENIUS_ACTION=PURCHASE
NGENIUS_PERSIST=true
NGENIUS_CACHE_STORE=redis
NGENIUS_LOG_ENABLED=true
NGENIUS_LOG_CHANNEL=stack
```

---

## Usage — Create a Payment

```php
use mbarky\Ngenius\Facades\Ngenius;
use mbarky\Ngenius\DTO\CreateOrderData;
use mbarky\Ngenius\DTO\Money;

$order = Ngenius::createPayment(
    CreateOrderData::make()
        ->amount(Money::aed(250.00))           // human-readable AED — converted to minor units internally
        ->email('customer@example.com')
        ->merchantOrderReference('INV-10025')
        ->for($invoice)                        // optional: polymorphic attachment to any Eloquent model
        ->redirectUrl(route('payment.callback'))
);

return redirect($order->paymentUrl());
```

> **⚠️ Minor units warning**
> `Money::aed(250.00)` converts to `25000` fils internally before sending to N-Genius.
> N-Genius always expects `amount.value` in **minor units as an integer**.
> Never pass a raw integer or float directly — always use the `Money` DTO.

---

## Usage — Callback / Return URL

N-Genius appends `?ref={orderReference}` to your `redirectUrl` when the customer returns.

```php
use mbarky\Ngenius\Facades\Ngenius;

// In your callback route handler:
public function callback(Request $request)
{
    $ref   = $request->query('ref');
    $order = Ngenius::retrieveOrder($ref);   // always verifies via API

    if ($order->isPaid()) {
        // mark invoice paid, redirect to success page…
    } else {
        // handle pending/failed…
    }
}
```

> **⚠️ Never trust the return URL alone.**
> Always call `Ngenius::retrieveOrder($ref)` to obtain the true payment state.
> The `ref` query parameter is informational only — it can be forged.

---

## Usage — Retrieve Order Status

```php
$order = Ngenius::retrieveOrder('ORD-REFERENCE-FROM-NGENIUS');

$order->orderReference;   // string
$order->state;            // PaymentState enum
$order->amount;           // Money DTO
$order->isPaid();         // true for PURCHASED | CAPTURED | AUTHORISED
$order->isFailed();       // true for FAILED | DECLINED | CANCELLED | ORDER_CLOSED
$order->isTerminal();     // true when no further state changes expected
```

---

## Webhooks

### Setup

1. In the N-Genius portal, configure your webhook URL: `https://yourapp.com/ngenius/webhook`
2. Set a custom secret header (e.g. `X-Ngenius-Hmac-Sha256: your-secret`) in the portal.
3. Set the same values in your `.env`:

```dotenv
NGENIUS_WEBHOOK_HEADER_NAME=X-Ngenius-Hmac-Sha256
NGENIUS_WEBHOOK_HEADER_VALUE=your-secret
```

### Important behaviours

- N-Genius sends **one POST per event with NO retries**. A missed webhook is lost forever.
- You must respond **HTTP 200 within 15 seconds**. The package responds immediately and queues heavy processing.
- Multiple events may arrive for one order in quick succession.
- Processing is **idempotent** — duplicate `eventId`s are silently skipped.
- The package always **reconciles via Retrieve Order Status** after receiving a webhook. It never trusts the payload state alone.

### Exclude from CSRF

Add the webhook route to your `VerifyCsrfToken` middleware exclusions:

```php
// app/Http/Middleware/VerifyCsrfToken.php
protected $except = [
    'ngenius/webhook',
];
```

---

## Events

Listen to these events in your `EventServiceProvider`:

| Event | Fired when |
|-------|-----------|
| `PaymentAuthorised` | Webhook `AUTHORISED` received and verified |
| `PaymentPurchased` | Webhook `PURCHASED` received and verified |
| `PaymentCaptured` | Webhook `CAPTURED` received and verified |
| `PaymentFailed` | Webhook `FAILED` or `DECLINED` received |
| `PaymentRefunded` | Webhook `REFUNDED` / `PARTIALLY_REFUNDED` *(v1.1)* |
| `WebhookReceived` | Any verified, non-duplicate webhook delivery |

```php
use mbarky\Ngenius\Events\PaymentPurchased;

class HandlePayment
{
    public function handle(PaymentPurchased $event): void
    {
        $event->order->orderReference;   // NgeniusOrder DTO
        $event->webhookPayload->eventId; // WebhookPayload DTO
    }
}
```

---

## Persistence

When `persist_transactions = true` (the default), every payment lifecycle is recorded in `ngenius_payments`:

| Column | Description |
|--------|-------------|
| `ngenius_order_reference` | N-Genius order ref (unique) |
| `merchant_order_reference` | Your internal reference |
| `amount_minor` | Amount in **minor units** |
| `currency` | ISO 4217 code |
| `status` | Current `PaymentState` value |
| `payment_url` | HPP redirect URL |
| `raw_order_response` | Full create-order JSON |
| `raw_status_response` | Latest retrieve-order JSON |
| `last_webhook_event_id` | For idempotency checks |
| `paid_at` / `failed_at` / `refunded_at` | Lifecycle timestamps |
| `payable_type` / `payable_id` | Polymorphic host model |

Set `NGENIUS_PERSIST=false` to disable the table entirely (e.g. when managing state yourself).

---

## Artisan Health Check

```bash
php artisan ngenius:health
```

Checks: environment, API key present, outlet reference present, base URL reachable, access token fetch, webhook route registered.

---

## Testing your application

Use `Http::fake()` to mock N-Genius responses in your own tests:

```php
use Illuminate\Support\Facades\Http;
use mbarky\Ngenius\Facades\Ngenius;

Http::fake([
    '*/identity/auth/access-token'      => Http::response(['access_token' => 'test-token'], 200),
    '*/transactions/outlets/*/orders'   => Http::response([
        'reference' => 'ORD-TEST-001',
        'amount'    => ['currencyCode' => 'AED', 'value' => 25000],
        '_embedded' => ['payment' => [['state' => 'STARTED']]],
        '_links'    => ['payment' => ['href' => 'https://hpp.example.com/pay/test']],
    ], 201),
]);

$order = Ngenius::createPayment(
    CreateOrderData::make()->amount(Money::aed(250.00))->email('test@example.com')
);

expect($order->paymentUrl())->toBe('https://hpp.example.com/pay/test');
```

---

## Security Notes

- **Never log or persist the API key or access token.** The package explicitly avoids this.
- **Always verify order status server-side** via `Ngenius::retrieveOrder()` — never trust client-supplied `ref` or webhook payload states alone.
- **Webhook payloads are not signed** by N-Genius. Origin is verified via a shared secret header. Keep `NGENIUS_WEBHOOK_HEADER_VALUE` secret and rotate it periodically.
- **Webhook idempotency** is enforced by `eventId` — safe against duplicate delivery even if the queue retries `ProcessWebhookJob`.
- The package uses `hash_equals()` for constant-time header comparison to prevent timing attacks.

---

## Roadmap

| Version | Scope |
|---------|-------|
| **v1.0** | ✅ Hosted Payment Page (HPP) — PURCHASE / AUTH / SALE |
| v1.1 | Refund / Partial-Refund |
| v1.2 | AUTH + Capture / Void / Reversal |
| v2.0 | Direct API / Hosted Session / 3DS / Tokenized cards |

---

## License

MIT — see [LICENSE.md](LICENSE.md).

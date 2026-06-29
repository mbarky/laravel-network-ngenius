# Changelog

All notable changes to `mbarky/laravel-network-ngenius` will be documented here.

This project adheres to [Semantic Versioning](https://semver.org/).

## [Unreleased]

## [1.0.0] — 2026-06-29

### Added
- Hosted Payment Page (HPP) flow — `PURCHASE`, `AUTH`, `SALE` actions
- `Ngenius::createPayment(CreateOrderData)` Facade method
- `Ngenius::retrieveOrder(string $ref)` Facade method (always verifies status via API)
- `Money` value object with minor-unit conversion and currency guards
- `CreateOrderData` fluent immutable builder with polymorphic `->for($model)` association
- `TokenManager` — cached access tokens (270 s TTL, auto-refresh)
- `NgeniusClient` — HAL-aware HTTP client; all follow-up URLs extracted from `_links`
- `HasHalLinks` concern — centralised HATEOAS link extraction
- Webhook endpoint with `VerifyNgeniusWebhook` secret-header middleware
- `WebhookService` — idempotent processing keyed on `eventId`, fast 200 + queued work
- `ProcessWebhookJob` — queued webhook processing
- Laravel events: `PaymentAuthorised`, `PaymentPurchased`, `PaymentCaptured`, `PaymentFailed`, `PaymentRefunded`, `WebhookReceived`
- `NgeniusPayment` Eloquent model with polymorphic `payable` relation
- Migration: `ngenius_payments` table
- `NgeniusPaymentRepository` — optional persistence gated by `persist_transactions`
- `CallbackController` — always reconciles via Retrieve Order Status
- `ngenius:health` Artisan command
- `config/ngenius.php` — full environment / credentials / webhook / cache / logging config
- `NgeniusServiceProvider` with auto-discovery
- `Ngenius` Facade
- PHPStan level-max config, Laravel Pint code style
- GitHub Actions CI matrix (PHP 8.2/8.3 × Laravel 10/11/12)
- 77 tests, 134 assertions

### Extension points (not yet built)
- v1.1 — Refund / Partial-Refund flow
- v1.2 — AUTH + Capture / Void / Reversal
- v2.0 — Direct API / Hosted Session / 3DS / tokenized cards

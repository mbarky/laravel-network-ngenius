<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ngenius_payments', function (Blueprint $table) {
            $table->id();

            // Polymorphic association — attaches to any host model.
            $table->nullableMorphs('payable');

            // Order references
            $table->string('merchant_order_reference')->nullable()->index();
            $table->string('ngenius_order_reference')->nullable()->unique();
            $table->string('ngenius_payment_reference')->nullable();
            $table->string('ngenius_capture_reference')->nullable();  // v1.2 — AUTH+Capture
            $table->string('outlet_reference')->nullable();

            // Payment details
            $table->string('action')->nullable();        // PURCHASE | AUTH | SALE
            $table->string('currency', 3)->nullable();
            $table->unsignedBigInteger('amount_minor')->nullable(); // ALWAYS minor units
            $table->string('status')->nullable()->index();
            $table->string('payment_url')->nullable();

            // Raw API payloads
            $table->json('raw_order_response')->nullable();
            $table->json('raw_status_response')->nullable();

            // Webhook tracking
            $table->string('last_webhook_event')->nullable();
            $table->string('last_webhook_event_id')->nullable()->index();
            $table->json('last_webhook_payload')->nullable();

            // Lifecycle timestamps
            $table->timestamp('paid_at')->nullable();
            $table->timestamp('failed_at')->nullable();
            $table->timestamp('refunded_at')->nullable();  // v1.1 — Refund flow

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ngenius_payments');
    }
};

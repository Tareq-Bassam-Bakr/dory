<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Billing records (docs/PAYMENTS.md): invoices, online checkouts and the
 * payment provider's webhook notifications. No card data is ever stored.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('billing_invoices', function (Blueprint $table) {
            $table->id();
            $table->ulid('public_id')->unique();
            $table->string('number', 30)->nullable()->unique();
            $table->foreignId('business_id')->constrained()->cascadeOnDelete();
            $table->foreignId('subscription_id')->nullable()->constrained('business_subscriptions')->nullOnDelete();
            $table->foreignId('plan_id')->constrained('subscription_plans');
            $table->string('billing_period', 10);
            $table->char('currency', 3);
            $table->unsignedBigInteger('amount_minor');
            // open, paid, failed, void.
            $table->string('status', 10);
            $table->timestamp('period_start')->nullable();
            $table->timestamp('period_end')->nullable();
            // An explicit default: MariaDB would otherwise give a NOT NULL timestamp
            // an implicit ON UPDATE CURRENT_TIMESTAMP or an invalid zero default
            // (see 2026_11_07_100400). The application always sets it.
            $table->timestamp('issued_at')->useCurrent();
            $table->timestamp('paid_at')->nullable();
            $table->timestamp('failed_at')->nullable();
            $table->string('failure_reason', 120)->nullable();
            // manual, mastercard, …
            $table->string('provider', 30);
            // card, bank_transfer, cash, mobile_wallet, other.
            $table->string('payment_method', 20)->nullable();
            $table->string('provider_reference', 120)->nullable();
            $table->foreignId('recorded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('notes', 500)->nullable();
            $table->timestamps();

            $table->unique(['provider', 'provider_reference']);
            $table->index(['business_id', 'issued_at']);
        });

        Schema::create('payment_checkouts', function (Blueprint $table) {
            $table->id();
            // Also the order id sent to the provider.
            $table->ulid('public_id')->unique();
            $table->foreignId('business_id')->constrained()->cascadeOnDelete();
            $table->foreignId('plan_id')->constrained('subscription_plans');
            $table->foreignId('invoice_id')->nullable()->constrained('billing_invoices')->nullOnDelete();
            $table->string('billing_period', 10);
            $table->char('currency', 3);
            $table->unsignedBigInteger('amount_minor');
            $table->string('provider', 30);
            // pending, paid, failed, expired.
            $table->string('status', 10);
            $table->string('provider_session_id', 120)->nullable();
            // SHA-256 of the provider's success indicator, never the value.
            $table->char('success_indicator_hash', 64)->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            // An explicit default: MariaDB would otherwise give a NOT NULL timestamp
            // an implicit ON UPDATE CURRENT_TIMESTAMP or an invalid zero default
            // (see 2026_11_07_100400). The application always sets it.
            $table->timestamp('expires_at')->useCurrent();
            $table->timestamp('completed_at')->nullable();
            $table->string('failure_reason', 120)->nullable();
            $table->timestamps();

            $table->index(['business_id', 'status']);
            // The sweep: pending checkouts past their expiry.
            $table->index(['status', 'expires_at']);
        });

        Schema::create('payment_webhook_events', function (Blueprint $table) {
            $table->id();
            $table->string('provider', 30);
            $table->string('event_id', 120);
            // processed, ignored, failed.
            $table->string('status', 10);
            // Whitelisted fields only (order id, result, status, amount).
            $table->json('summary')->nullable();
            $table->string('error', 255)->nullable();
            // An explicit default: MariaDB would otherwise give a NOT NULL timestamp
            // an implicit ON UPDATE CURRENT_TIMESTAMP or an invalid zero default
            // (see 2026_11_07_100400). The application always sets it.
            $table->timestamp('received_at')->useCurrent();
            $table->timestamp('processed_at')->nullable();
            $table->timestamps();

            $table->unique(['provider', 'event_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payment_webhook_events');
        Schema::dropIfExists('payment_checkouts');
        Schema::dropIfExists('billing_invoices');
    }
};

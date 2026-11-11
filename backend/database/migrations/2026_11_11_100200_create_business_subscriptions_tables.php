<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * One subscription per business, and its history (docs/SUBSCRIPTIONS.md).
 * The subscription belongs to the business, never to a person.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('business_subscriptions', function (Blueprint $table) {
            $table->id();
            $table->ulid('public_id')->unique();
            $table->foreignId('business_id')->unique()->constrained()->cascadeOnDelete();
            $table->foreignId('plan_id')->constrained('subscription_plans');
            // trialing, active, past_due, expired.
            $table->string('status', 20);
            // Paid periods only.
            $table->string('billing_period', 10)->nullable();
            $table->char('currency', 3)->nullable();
            $table->unsignedBigInteger('amount_minor')->nullable();
            $table->timestamp('trial_ends_at')->nullable();
            $table->timestamp('current_period_start')->nullable();
            $table->timestamp('current_period_end')->nullable();
            $table->timestamp('grace_ends_at')->nullable();
            $table->boolean('cancel_at_period_end')->default(false);
            $table->timestamp('canceled_at')->nullable();
            $table->timestamp('ended_at')->nullable();
            // A downgrade waiting for the end of the paid period.
            $table->foreignId('scheduled_plan_id')->nullable()->constrained('subscription_plans')->nullOnDelete();
            $table->string('scheduled_billing_period', 10)->nullable();
            $table->string('provider', 30)->nullable();
            $table->timestamps();

            // The scheduler's three sweeps.
            $table->index(['status', 'trial_ends_at']);
            $table->index(['status', 'current_period_end']);
            $table->index(['status', 'grace_ends_at']);
        });

        Schema::create('subscription_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('business_id')->constrained()->cascadeOnDelete();
            $table->foreignId('subscription_id')->constrained('business_subscriptions')->cascadeOnDelete();
            $table->string('type', 40);
            $table->foreignId('from_plan_id')->nullable()->constrained('subscription_plans')->nullOnDelete();
            $table->foreignId('to_plan_id')->nullable()->constrained('subscription_plans')->nullOnDelete();
            $table->string('from_status', 20)->nullable();
            $table->string('to_status', 20)->nullable();
            $table->foreignId('actor_id')->nullable()->constrained('users')->nullOnDelete();
            $table->json('metadata')->nullable();
            // An explicit default: MariaDB would otherwise give a NOT NULL timestamp
            // an implicit ON UPDATE CURRENT_TIMESTAMP or an invalid zero default
            // (see 2026_11_07_100400). The application always sets it.
            $table->timestamp('occurred_at')->useCurrent();
            $table->timestamps();

            $table->index(['business_id', 'occurred_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('subscription_events');
        Schema::dropIfExists('business_subscriptions');
    }
};

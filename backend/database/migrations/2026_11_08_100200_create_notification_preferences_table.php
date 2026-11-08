<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * What each user wants to be told (docs/NOTIFICATION_CENTER.md). A user
 * without a row gets the defaults: everything on.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('notification_preferences', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->unique()->constrained()->cascadeOnDelete();
            // Called, service started and completed, cancelled, skipped, no-show.
            $table->boolean('ticket_updates')->default(true);
            $table->boolean('almost_turn')->default(true);
            // The queue was paused or closed.
            $table->boolean('queue_updates')->default(true);
            // Staff: a customer joined, a customer cancelled.
            $table->boolean('staff_new_tickets')->default(true);
            $table->boolean('staff_cancellations')->default(true);
            // Off: notifications stay in the app, nothing is pushed to devices.
            $table->boolean('push_enabled')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('notification_preferences');
    }
};

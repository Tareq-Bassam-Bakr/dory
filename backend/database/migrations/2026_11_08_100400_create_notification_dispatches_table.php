<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Once-only ledger of notifications (docs/NOTIFICATION_EVENTS.md).
 *
 * Every alert has a key, such as "event:{queue event}:user:{user}" or
 * "ticket:{ticket}:almost_turn". The row is written in the same transaction
 * as the notification: a retried job, or a second worker, finds the key
 * and sends nothing.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('notification_dispatches', function (Blueprint $table) {
            $table->id();
            $table->string('dedupe_key', 191)->unique();
            $table->timestamp('created_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('notification_dispatches');
    }
};

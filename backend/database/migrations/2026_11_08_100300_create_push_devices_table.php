<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Devices that receive push notifications (docs/DEVICE_TOKEN_SECURITY.md).
 *
 * A row belongs either to a signed-in user or to one guest ticket. The FCM
 * token is encrypted at rest; token_hash (SHA-256) allows lookups and
 * uniqueness without decrypting. owner_key ("user:{id}" or "ticket:{id}")
 * makes one row per token and owner.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('push_devices', function (Blueprint $table) {
            $table->id();
            $table->ulid('public_id')->unique();
            $table->foreignId('user_id')->nullable()->constrained()->cascadeOnDelete();
            $table->foreignId('queue_ticket_id')->nullable()->constrained('queue_tickets')->cascadeOnDelete();
            $table->string('owner_key', 40);
            $table->text('token_encrypted');
            $table->char('token_hash', 64);
            $table->string('platform', 16);
            $table->string('locale', 8)->default('en');
            $table->string('app_version', 32)->nullable();
            $table->timestamp('last_seen_at')->nullable();
            $table->timestamp('revoked_at')->nullable();
            $table->timestamps();

            $table->unique(['token_hash', 'owner_key']);
            $table->index(['user_id', 'revoked_at']);
            $table->index(['queue_ticket_id', 'revoked_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('push_devices');
    }
};

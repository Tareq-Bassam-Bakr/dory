<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * A permanent queue configuration for one service of one branch. Reused
     * across operating days; operating periods are queue_sessions.
     */
    public function up(): void
    {
        Schema::create('queues', function (Blueprint $table) {
            $table->id();
            $table->ulid('public_id')->unique();
            $table->foreignId('branch_id')->constrained()->cascadeOnDelete();
            $table->foreignId('service_id')->constrained()->cascadeOnDelete();
            $table->string('name', 150);
            // Shown before the daily number: BAR-001. Upper-case letters/digits.
            $table->string('ticket_prefix', 6);
            $table->string('status', 20)->default('active');
            // Maximum WAITING tickets at once; NULL means no limit.
            $table->unsignedInteger('capacity')->nullable();
            $table->boolean('allow_guest_join')->default(true);
            $table->boolean('require_guest_name')->default(true);
            $table->boolean('require_guest_phone')->default(false);
            // Minutes per customer; NULL falls back to the service's duration.
            $table->unsignedSmallInteger('estimated_service_duration')->nullable();
            // Seconds a called customer has before a no-show may be recorded.
            $table->unsignedInteger('grace_period_seconds')->default(120);
            $table->json('settings')->nullable();
            // Public join link: lookup by hash, token kept encrypted so the
            // link can be shown again. Rotating replaces both.
            $table->char('public_token_hash', 64)->nullable()->unique();
            $table->text('public_token_encrypted')->nullable();
            $table->timestamp('public_token_rotated_at')->nullable();
            // Uniqueness among live queues only (NULL once deleted), so a
            // deleted queue frees its prefix and its service slot:
            //   active_prefix = "{branch_id}:{PREFIX}"  one prefix per branch
            //   active_slot   = "{service_id}:{lane}"   one queue per service (lane 1 in the MVP)
            $table->string('active_prefix', 40)->nullable()->unique();
            $table->string('active_slot', 40)->nullable()->unique();
            $table->unsignedTinyInteger('lane')->default(1);
            $table->timestamps();
            $table->softDeletes();

            $table->index(['branch_id', 'status']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('queues');
    }
};

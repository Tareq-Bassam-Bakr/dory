<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * An operating period of a queue. Sessions are never deleted, so they
     * remain available for history and reporting.
     */
    public function up(): void
    {
        Schema::create('queue_sessions', function (Blueprint $table) {
            $table->id();
            $table->ulid('public_id')->unique();
            $table->foreignId('queue_id')->constrained()->cascadeOnDelete();
            // Branch-local calendar date on which the session was opened.
            $table->date('business_date');
            // 1, 2, ... per queue and business date.
            $table->unsignedSmallInteger('session_number');
            $table->string('status', 20)->default('open');
            $table->timestamp('opened_at');
            $table->timestamp('paused_at')->nullable();
            $table->timestamp('closed_at')->nullable();
            $table->foreignId('opened_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('closed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('close_reason', 40)->nullable();
            // "{queue_id}" while open or paused, NULL once closed: at most one
            // active session per queue, enforced by the database.
            $table->string('active_key', 40)->nullable()->unique();
            $table->timestamps();

            $table->unique(['queue_id', 'business_date', 'session_number']);
            $table->index(['queue_id', 'status']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('queue_sessions');
    }
};

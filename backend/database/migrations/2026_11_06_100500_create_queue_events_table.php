<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Append-only history of queue activity: ticket transitions and session
     * changes. Written in the same transaction as the change it records;
     * never updated or deleted by the application.
     */
    public function up(): void
    {
        Schema::create('queue_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('queue_id')->constrained()->cascadeOnDelete();
            $table->foreignId('queue_session_id')->nullable()->constrained()->cascadeOnDelete();
            $table->foreignId('ticket_id')->nullable()->constrained('queue_tickets')->cascadeOnDelete();
            // NULL for guests and the system; metadata says which.
            $table->foreignId('actor_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('event_type', 40);
            $table->string('previous_status', 20)->nullable();
            $table->string('new_status', 20)->nullable();
            $table->json('metadata')->nullable();
            $table->timestamp('created_at');

            $table->index(['ticket_id', 'created_at']);
            $table->index(['queue_session_id', 'created_at']);
            $table->index(['queue_id', 'created_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('queue_events');
    }
};

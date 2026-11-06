<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * One customer's place in a queue session. Rows are never deleted:
     * finished tickets are history.
     */
    public function up(): void
    {
        Schema::create('queue_tickets', function (Blueprint $table) {
            $table->id();
            // Fully random (not time-based), so ids cannot be predicted.
            $table->char('public_id', 26)->unique();
            $table->foreignId('queue_session_id')->constrained()->cascadeOnDelete();
            // Denormalized from the session for numbering and lookups.
            $table->foreignId('queue_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            // Display number (BAR-001) and the daily sequence behind it.
            $table->string('ticket_number', 20);
            $table->unsignedInteger('sequence_number');
            // Branch-local date the number was issued for.
            $table->date('business_date');
            $table->string('status', 20)->default('waiting');
            // Reserved for future controlled priority; always 0 in Phase 04.
            $table->smallInteger('priority')->default(0);
            $table->string('guest_name', 80)->nullable();
            // Encrypted at rest (Laravel "encrypted" cast).
            $table->text('guest_phone')->nullable();
            // SHA-256 of the guest's ticket access token; plain token never stored.
            $table->char('public_access_token_hash', 64)->nullable();
            $table->string('join_source', 20);
            // SHA-256 of the client's Idempotency-Key, scoped to the joiner.
            $table->char('idempotency_key_hash', 64)->nullable();
            $table->timestamp('joined_at');
            $table->timestamp('called_at')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamp('cancelled_at')->nullable();
            // When the ticket reached any terminal status.
            $table->timestamp('ended_at')->nullable();
            $table->string('cancel_reason', 40)->nullable();
            $table->unsignedInteger('estimated_wait_seconds')->nullable();
            $table->timestamp('estimated_at')->nullable();
            // The staff member who called / serves the ticket.
            $table->foreignId('served_by')->nullable()->constrained('users')->nullOnDelete();
            $table->unsignedTinyInteger('recall_count')->default(0);
            // Database-enforced invariants, NULL when not applicable:
            //   active_user_key  = "{session_id}:{user_id}" while waiting/called/serving
            //                      (one active ticket per customer per session)
            //   active_staff_key = "{served_by}" while called/serving
            //                      (one active customer per staff member)
            $table->string('active_user_key', 40)->nullable()->unique();
            $table->string('active_staff_key', 40)->nullable()->unique();
            $table->timestamps();

            // Numbers are unique per queue and day, whatever happens above.
            $table->unique(['queue_id', 'business_date', 'sequence_number']);
            $table->unique(['queue_id', 'idempotency_key_hash']);
            // FIFO: waiting tickets of a session in order.
            $table->index(['queue_session_id', 'status', 'priority', 'id']);
            $table->index(['user_id', 'status']);
            $table->index(['served_by', 'status']);
            $table->index('public_access_token_hash');
            $table->index(['queue_id', 'status', 'completed_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('queue_tickets');
    }
};

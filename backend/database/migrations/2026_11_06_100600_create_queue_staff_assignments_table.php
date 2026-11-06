<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Which team members (existing business_members) work a queue. Ending an
     * assignment sets unassigned_at, so the history is kept.
     */
    public function up(): void
    {
        Schema::create('queue_staff_assignments', function (Blueprint $table) {
            $table->id();
            $table->ulid('public_id')->unique();
            $table->foreignId('queue_id')->constrained()->cascadeOnDelete();
            $table->foreignId('business_member_id')->constrained()->cascadeOnDelete();
            $table->foreignId('assigned_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('assigned_at');
            $table->foreignId('unassigned_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('unassigned_at')->nullable();
            // "{queue_id}:{business_member_id}" while active, NULL once ended.
            $table->string('active_key', 40)->nullable()->unique();
            $table->timestamps();

            $table->index(['queue_id', 'unassigned_at']);
            $table->index(['business_member_id', 'unassigned_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('queue_staff_assignments');
    }
};

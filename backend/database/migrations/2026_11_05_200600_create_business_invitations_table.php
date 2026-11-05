<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('business_invitations', function (Blueprint $table) {
            $table->id();
            $table->ulid('public_id')->unique();
            $table->foreignId('business_id')->constrained()->cascadeOnDelete();
            $table->string('email');
            $table->string('role', 20);
            $table->string('branch_access', 20)->default('all');
            // Internal branch ids for "selected" access. They are re-checked
            // against the business when the invitation is accepted.
            $table->json('branch_ids')->nullable();
            // SHA-256 of the token. The plain token is never stored or logged.
            $table->char('token_hash', 64)->unique();
            $table->string('status', 20)->default('pending');
            // sha256(business_id|email) while pending, NULL otherwise. The
            // unique index allows one pending invitation per email per
            // business, even under concurrent requests.
            $table->char('pending_key', 64)->nullable()->unique();
            $table->foreignId('invited_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('accepted_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('expires_at');
            $table->timestamp('responded_at')->nullable();
            $table->timestamps();

            $table->index(['business_id', 'status']);
            $table->index('email');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('business_invitations');
    }
};

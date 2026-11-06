<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * One rating per completed ticket, from the customer who held it
     * (docs/CUSTOMER_TICKET_EXPERIENCE.md). Ratings only: there is no text,
     * so nothing here needs moderation in this phase.
     */
    public function up(): void
    {
        Schema::create('ticket_reviews', function (Blueprint $table) {
            $table->id();
            $table->ulid('public_id')->unique();
            // Unique: the database itself refuses a second review of a ticket.
            $table->foreignId('queue_ticket_id')->unique()->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('business_id')->constrained()->cascadeOnDelete();
            $table->unsignedTinyInteger('rating');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('ticket_reviews');
    }
};

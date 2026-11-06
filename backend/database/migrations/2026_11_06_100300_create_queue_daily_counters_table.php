<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * The last ticket number issued per queue and branch-local date. The
     * row is incremented atomically inside the joining transaction (see
     * docs/TICKET_NUMBERING.md); numbers are never decremented or reused.
     */
    public function up(): void
    {
        Schema::create('queue_daily_counters', function (Blueprint $table) {
            $table->id();
            $table->foreignId('queue_id')->constrained()->cascadeOnDelete();
            $table->date('business_date');
            $table->unsignedInteger('last_number')->default(0);
            $table->timestamps();

            $table->unique(['queue_id', 'business_date']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('queue_daily_counters');
    }
};

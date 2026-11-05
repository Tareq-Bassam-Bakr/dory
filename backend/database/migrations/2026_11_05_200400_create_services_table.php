<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * One generic model for every sector: a clinic consultation, a bank
     * counter operation and a salon haircut are all services.
     */
    public function up(): void
    {
        Schema::create('services', function (Blueprint $table) {
            $table->id();
            $table->ulid('public_id')->unique();
            $table->foreignId('branch_id')->constrained()->cascadeOnDelete();
            $table->string('name', 150);
            $table->text('description')->nullable();
            $table->unsignedSmallInteger('estimated_duration_minutes')->default(15);
            $table->unsignedInteger('display_order')->default(0);
            $table->string('status', 20)->default('active');
            $table->timestamps();
            // Archived services keep their row for future ticket history.
            $table->softDeletes();

            $table->index(['branch_id', 'status', 'display_order']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('services');
    }
};

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
        Schema::create('business_categories', function (Blueprint $table) {
            $table->id();
            // Self-reference for future subcategories. Top-level categories have none.
            $table->foreignId('parent_id')->nullable()->constrained('business_categories')->restrictOnDelete();
            // Stable public identifier. Never changes once published.
            $table->string('slug', 80)->unique();
            // Translations keyed by language code: {"en": "...", "ar": "..."}.
            $table->json('name');
            // Icon key that clients map to their own icon set.
            $table->string('icon', 40)->default('other');
            $table->unsignedSmallInteger('sort_order')->default(0);
            // Disabled categories are hidden from clients and cannot be chosen
            // for new businesses; existing businesses keep them.
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index(['is_active', 'sort_order']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('business_categories');
    }
};

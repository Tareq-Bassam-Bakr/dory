<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Indexes behind the public discovery queries (docs/SEARCH_AND_FILTERS.md),
     * and the rating summary of each business. The summary is derived from
     * ticket_reviews and only ever written by TicketReviewService; a business
     * without reviews has no average at all, never a default one.
     */
    public function up(): void
    {
        Schema::table('businesses', function (Blueprint $table) {
            $table->decimal('rating_average', 3, 2)->nullable()->after('status');
            $table->unsignedInteger('rating_count')->default(0)->after('rating_average');

            $table->index(['status', 'name']);
            $table->index(['status', 'rating_average']);
        });

        Schema::table('branches', function (Blueprint $table) {
            $table->index(['status', 'country', 'city']);
            // Bounding-box lookups for nearby search.
            $table->index(['status', 'latitude', 'longitude']);
        });

        Schema::table('services', function (Blueprint $table) {
            $table->index(['status', 'name']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('services', function (Blueprint $table) {
            $table->dropIndex(['status', 'name']);
        });

        Schema::table('branches', function (Blueprint $table) {
            $table->dropIndex(['status', 'latitude', 'longitude']);
            $table->dropIndex(['status', 'country', 'city']);
        });

        Schema::table('businesses', function (Blueprint $table) {
            $table->dropIndex(['status', 'rating_average']);
            $table->dropIndex(['status', 'name']);
            $table->dropColumn(['rating_average', 'rating_count']);
        });
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Extends the Phase 02 businesses table with the business profile.
     * Ownership stays in business_members (role "owner"); created_by only
     * records who created the row.
     */
    public function up(): void
    {
        Schema::table('businesses', function (Blueprint $table) {
            // Nullable for rows created before categories existed. The API
            // requires a category for every new business. Categories in use
            // are disabled, never deleted.
            $table->foreignId('category_id')->nullable()->after('public_id')
                ->constrained('business_categories')->restrictOnDelete();
            $table->string('email')->nullable()->after('description');
            $table->string('phone', 20)->nullable()->after('email');
            $table->string('website')->nullable()->after('phone');
            $table->string('timezone', 64)->default('UTC')->after('website');
            // Storage paths, set only by server-side upload handling.
            $table->string('logo_path')->nullable()->after('timezone');
            $table->string('cover_path')->nullable()->after('logo_path');
            // Client-generated key that makes "create business" safe to retry.
            $table->string('idempotency_key', 64)->nullable()->after('created_by');
            $table->softDeletes();

            $table->unique(['created_by', 'idempotency_key']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('businesses', function (Blueprint $table) {
            // MySQL and MariaDB drop the index they created for the
            // created_by foreign key as soon as the (created_by,
            // idempotency_key) index can serve that key, and then refuse to
            // drop the unique index (error 1553). So the foreign key goes
            // first and is re-created below, which also re-creates its index.
            $table->dropForeign(['created_by']);
            $table->dropUnique(['created_by', 'idempotency_key']);
            $table->dropConstrainedForeignId('category_id');
            $table->dropColumn([
                'email', 'phone', 'website', 'timezone', 'logo_path', 'cover_path',
                'idempotency_key', 'deleted_at',
            ]);
        });

        Schema::table('businesses', function (Blueprint $table) {
            $table->foreign('created_by')->references('id')->on('users')->nullOnDelete();
        });
    }
};

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
        Schema::create('branches', function (Blueprint $table) {
            $table->id();
            $table->ulid('public_id')->unique();
            $table->foreignId('business_id')->constrained()->cascadeOnDelete();
            $table->string('name', 150);
            $table->string('address', 255)->nullable();
            // ISO 3166-1 alpha-2 country code, e.g. "SY", "EG".
            $table->char('country', 2)->nullable();
            $table->string('city', 120)->nullable();
            $table->string('region', 120)->nullable();
            // WGS 84 decimal degrees, the coordinate system of OpenStreetMap.
            // Seven decimals is about one centimetre of precision.
            $table->decimal('latitude', 10, 7)->nullable();
            $table->decimal('longitude', 10, 7)->nullable();
            $table->string('phone', 20)->nullable();
            $table->string('email')->nullable();
            $table->string('timezone', 64)->default('UTC');
            $table->string('status', 20)->default('active');
            $table->timestamps();
            // Deleted branches keep their row for future queue history.
            $table->softDeletes();

            $table->index(['business_id', 'status']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('branches');
    }
};

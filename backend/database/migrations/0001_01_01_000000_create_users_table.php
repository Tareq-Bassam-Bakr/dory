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
        Schema::create('users', function (Blueprint $table) {
            $table->id();
            // Public identifier exposed by the API. The numeric id stays internal.
            $table->ulid('public_id')->unique();
            $table->string('name', 120);
            $table->string('email')->unique();
            // E.164 format (e.g. +963912345678). Unique so it can become a login identifier.
            $table->string('phone', 20)->nullable()->unique();
            $table->string('password');
            $table->string('locale', 10)->default('en');
            $table->string('timezone', 64)->default('UTC');
            $table->string('status', 20)->default('active')->index();
            // Platform-wide privilege. Independent of any business membership and
            // never assignable through registration or profile updates.
            $table->boolean('is_platform_admin')->default(false);
            $table->timestamp('email_verified_at')->nullable();
            $table->timestamp('last_login_at')->nullable();
            $table->timestamps();
        });

        Schema::create('password_reset_tokens', function (Blueprint $table) {
            $table->string('email')->primary();
            $table->string('token');
            $table->timestamp('created_at')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('password_reset_tokens');
        Schema::dropIfExists('users');
    }
};

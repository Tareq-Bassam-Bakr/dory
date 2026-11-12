<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
 * The platform admin dashboard (docs/ADMIN_DASHBOARD.md):
 *
 * - admin_sessions: sign-in sessions of /admin only. The API keeps no
 *   sessions; this table is never used for it.
 * - platform_audit_logs: every sensitive platform operation, append-only.
 * - platform_settings: the few commercial settings admins may change at run
 *   time (trial, grace days, currencies, ...); empty means the defaults from
 *   config/ apply.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('admin_sessions', function (Blueprint $table) {
            $table->string('id')->primary();
            $table->foreignId('user_id')->nullable()->index();
            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->longText('payload');
            $table->integer('last_activity')->index();
        });

        Schema::create('platform_audit_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('actor_id')->nullable()->constrained('users')->nullOnDelete();
            // Who acted when there is no account: "console" for artisan commands.
            $table->string('actor_label', 120)->nullable();
            $table->string('action', 60)->index();
            $table->foreignId('business_id')->nullable()->constrained()->nullOnDelete();
            $table->string('subject_type', 60)->nullable();
            $table->unsignedBigInteger('subject_id')->nullable();
            $table->json('properties')->nullable();
            $table->string('ip_address', 45)->nullable();
            $table->string('user_agent', 255)->nullable();
            // An explicit default: see 2026_11_07_100400 (MariaDB timestamps).
            $table->timestamp('created_at')->useCurrent()->index();

            $table->index(['subject_type', 'subject_id']);
        });

        Schema::create('platform_settings', function (Blueprint $table) {
            $table->string('key', 80)->primary();
            $table->json('value');
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('platform_settings');
        Schema::dropIfExists('platform_audit_logs');
        Schema::dropIfExists('admin_sessions');
    }
};

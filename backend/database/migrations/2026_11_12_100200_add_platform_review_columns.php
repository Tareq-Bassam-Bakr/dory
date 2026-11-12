<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
 * Platform review fields (docs/ADMIN_DASHBOARD.md). Only new nullable
 * columns: existing rows and behaviour are unchanged.
 *
 * - businesses: approval by a platform admin, and why and since when a
 *   business is suspended (with its status before, restored on
 *   reactivation).
 * - billing_invoices: payments received outside DORY count once a platform
 *   admin reviewed them; received_at is when the money arrived.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('businesses', function (Blueprint $table) {
            $table->timestamp('approved_at')->nullable()->after('status');
            $table->foreignId('approved_by')->nullable()->after('approved_at')->constrained('users')->nullOnDelete();
            $table->timestamp('suspended_at')->nullable()->after('approved_by');
            $table->string('suspension_reason', 255)->nullable()->after('suspended_at');
            $table->string('status_before_suspension', 20)->nullable()->after('suspension_reason');
        });

        Schema::table('billing_invoices', function (Blueprint $table) {
            $table->timestamp('received_at')->nullable()->after('paid_at');
            $table->foreignId('reviewed_by')->nullable()->after('recorded_by')->constrained('users')->nullOnDelete();
            $table->timestamp('reviewed_at')->nullable()->after('reviewed_by');
        });
    }

    public function down(): void
    {
        Schema::table('billing_invoices', function (Blueprint $table) {
            $table->dropConstrainedForeignId('reviewed_by');
            $table->dropColumn(['received_at', 'reviewed_at']);
        });

        Schema::table('businesses', function (Blueprint $table) {
            $table->dropConstrainedForeignId('approved_by');
            $table->dropColumn(['approved_at', 'suspended_at', 'suspension_reason', 'status_before_suspension']);
        });
    }
};

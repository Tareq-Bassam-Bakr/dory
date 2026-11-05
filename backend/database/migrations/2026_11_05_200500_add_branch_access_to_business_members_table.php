<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Gives memberships a public identifier (for /members/{member} routes)
     * and branch scoping: "all" branches, or only the "selected" branches
     * listed in business_member_branch.
     */
    public function up(): void
    {
        Schema::table('business_members', function (Blueprint $table) {
            $table->ulid('public_id')->nullable()->after('id');
            $table->string('branch_access', 20)->default('all')->after('status');
        });

        // Backfill memberships created before this migration.
        DB::table('business_members')->whereNull('public_id')->lazyById()
            ->each(function (object $row): void {
                DB::table('business_members')->where('id', $row->id)
                    ->update(['public_id' => strtolower((string) Str::ulid())]);
            });

        Schema::table('business_members', function (Blueprint $table) {
            $table->ulid('public_id')->nullable(false)->change();
            $table->unique('public_id');
        });

        Schema::create('business_member_branch', function (Blueprint $table) {
            $table->id();
            $table->foreignId('business_member_id')->constrained()->cascadeOnDelete();
            $table->foreignId('branch_id')->constrained()->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['business_member_id', 'branch_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('business_member_branch');

        Schema::table('business_members', function (Blueprint $table) {
            $table->dropUnique(['public_id']);
            $table->dropColumn(['public_id', 'branch_access']);
        });
    }
};

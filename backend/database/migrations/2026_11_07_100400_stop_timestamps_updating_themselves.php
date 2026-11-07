<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Columns declared as a plain NOT NULL timestamp. With
     * explicit_defaults_for_timestamp off (MariaDB's default) the first one
     * in a table silently gets ON UPDATE CURRENT_TIMESTAMP, so a ticket's
     * join time or an invitation's expiry changed on any update of the row.
     *
     * @var array<string, string>
     */
    private const COLUMNS = [
        'business_invitations' => 'expires_at',
        'queue_sessions' => 'opened_at',
        'queue_tickets' => 'joined_at',
        'queue_events' => 'created_at',
        'queue_staff_assignments' => 'assigned_at',
    ];

    /**
     * Run the migrations.
     *
     * An explicit default removes the implicit clause. The application always
     * sets these columns itself, so the default is never used. MySQL 8.4 has
     * explicit_defaults_for_timestamp on and never had the problem; SQLite
     * has no such clause.
     */
    public function up(): void
    {
        if (! in_array(DB::connection()->getDriverName(), ['mysql', 'mariadb'], true)) {
            return;
        }

        foreach (self::COLUMNS as $table => $column) {
            DB::statement("alter table `{$table}` modify `{$column}` timestamp not null default current_timestamp");
        }
    }

    /**
     * Reverse the migrations.
     *
     * Deliberately empty: there is no reason to bring the bug back, and the
     * tables are dropped by their own migrations.
     */
    public function down(): void
    {
        //
    }
};

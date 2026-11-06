<?php

namespace Tests\Feature\Database;

use Illuminate\Foundation\Testing\RefreshDatabaseState;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/**
 * Every migration must be reversible on every supported database. A full
 * rollback on MySQL 8.4 found a down() that failed on MySQL and MariaDB but
 * not on SQLite (see the businesses profile migration), so the whole chain is
 * rolled back here, not only in CI.
 *
 * Runs on SQLite in the default suite and on MariaDB/MySQL with
 * `composer test:mariadb` / `composer test:mysql`. It changes the schema
 * itself, so it does not use RefreshDatabase's transaction.
 */
class MigrationRollbackTest extends TestCase
{
    public function test_every_migration_can_be_rolled_back_and_run_again(): void
    {
        // If anything below fails, the next test rebuilds the schema.
        RefreshDatabaseState::$migrated = false;

        $this->artisan('migrate:fresh')->assertSuccessful();
        $tables = $this->tableNames();
        $this->assertContains('queue_tickets', $tables);

        // Runs every down(), newest first.
        $this->artisan('migrate:reset')->assertSuccessful();
        $this->assertSame(['migrations'], $this->tableNames(), 'A down() left tables behind.');

        $this->artisan('migrate')->assertSuccessful();
        $this->assertSame($tables, $this->tableNames());

        // Freshly migrated and empty: what RefreshDatabase expects.
        RefreshDatabaseState::$migrated = true;
    }

    /**
     * Tables of the test database only: without a schema, MySQL and MariaDB
     * list every database the user can see.
     *
     * @return list<string>
     */
    private function tableNames(): array
    {
        return collect(Schema::getTables(Schema::getCurrentSchemaListing()))
            ->pluck('name')
            ->reject(fn (string $name): bool => str_starts_with($name, 'sqlite_'))
            ->sort()
            ->values()
            ->all();
    }
}

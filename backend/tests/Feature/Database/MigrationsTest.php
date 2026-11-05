<?php

namespace Tests\Feature\Database;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/**
 * Runs on SQLite in the default suite and on MariaDB/MySQL with
 * `composer test:mariadb` / `composer test:mysql`.
 */
class MigrationsTest extends TestCase
{
    use RefreshDatabase;

    public function test_all_foundation_tables_exist(): void
    {
        foreach (['users', 'password_reset_tokens', 'personal_access_tokens', 'businesses', 'business_members', 'cache', 'jobs'] as $table) {
            $this->assertTrue(Schema::hasTable($table), "Missing table: {$table}");
        }

        $this->assertFalse(Schema::hasTable('sessions'), 'The API is stateless; no sessions table.');
    }

    public function test_users_table_has_the_required_columns(): void
    {
        $this->assertTrue(Schema::hasColumns('users', [
            'id', 'public_id', 'name', 'email', 'phone', 'password', 'locale', 'timezone',
            'status', 'is_platform_admin', 'email_verified_at', 'last_login_at', 'created_at', 'updated_at',
        ]));
    }

    public function test_businesses_table_has_the_required_columns(): void
    {
        $this->assertTrue(Schema::hasColumns('businesses', [
            'id', 'public_id', 'name', 'slug', 'description', 'status', 'created_by', 'created_at', 'updated_at',
        ]));
    }

    public function test_business_members_table_has_the_required_columns(): void
    {
        $this->assertTrue(Schema::hasColumns('business_members', [
            'id', 'business_id', 'user_id', 'role', 'status', 'joined_at', 'created_at', 'updated_at',
        ]));
    }

    public function test_unique_constraints_are_in_place(): void
    {
        $uniqueIndexes = fn (string $table): array => collect(Schema::getIndexes($table))
            ->where('unique', true)
            ->pluck('columns')
            ->all();

        $this->assertContains(['email'], $uniqueIndexes('users'));
        $this->assertContains(['phone'], $uniqueIndexes('users'));
        $this->assertContains(['public_id'], $uniqueIndexes('users'));
        $this->assertContains(['public_id'], $uniqueIndexes('businesses'));
        $this->assertContains(['slug'], $uniqueIndexes('businesses'));
        $this->assertContains(['business_id', 'user_id'], $uniqueIndexes('business_members'));
    }

    public function test_membership_foreign_keys_cascade_on_delete(): void
    {
        $foreignKeys = collect(Schema::getForeignKeys('business_members'))
            ->mapWithKeys(fn (array $fk) => [$fk['columns'][0] => [$fk['foreign_table'], $fk['on_delete']]]);

        $this->assertSame(['businesses', 'cascade'], $foreignKeys['business_id']);
        $this->assertSame(['users', 'cascade'], $foreignKeys['user_id']);
    }
}

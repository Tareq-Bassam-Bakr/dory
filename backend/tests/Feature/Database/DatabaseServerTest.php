<?php

namespace Tests\Feature\Database;

use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * `composer test:mysql` and `composer test:mariadb` say which server they
 * are for (DORY_DATABASE_SERVER). This test fails when the suite reached
 * another one, so a result can never be reported for the wrong database.
 *
 * Found in Phase 09: the scripted Phase 08 "MySQL 8.4" run did not load the
 * MySQL 8.4 credentials and ran against MariaDB on port 3306
 * (docs/MYSQL_84_COMPATIBILITY.md).
 */
class DatabaseServerTest extends TestCase
{
    public function test_the_suite_runs_on_the_server_it_was_started_for(): void
    {
        $expected = env('DORY_DATABASE_SERVER');
        if (! is_string($expected) || $expected === '') {
            $this->markTestSkipped('Only for composer test:mysql and composer test:mariadb.');
        }

        $version = (string) DB::selectOne('select version() as v')->v;

        if ($expected === 'mysql') {
            $this->assertStringNotContainsStringIgnoringCase('mariadb', $version,
                "composer test:mysql reached $version. Load the MySQL 8.4 credentials first (docs/MYSQL_84_COMPATIBILITY.md).");
            $this->assertStringStartsWith('8.4.', $version, "composer test:mysql reached MySQL $version, not 8.4.");
        } else {
            $this->assertSame('mariadb', $expected);
            $this->assertStringContainsStringIgnoringCase('mariadb', $version, "composer test:mariadb reached $version.");
        }
    }
}

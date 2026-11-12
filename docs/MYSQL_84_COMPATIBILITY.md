# MySQL 8.4 compatibility

Production targets MySQL 8.4 LTS; local development uses XAMPP's MariaDB 10.4.

## Status (2026-11-12, end of Phase 09)

| Database | How | Result |
|---|---|---|
| SQLite (in memory) | `composer test` | ✅ 811 passed, 10 skipped (the tests that need MySQL or MariaDB, and the server check) |
| MariaDB 10.4 (XAMPP) | `composer test:mariadb` | ✅ 821 passed (10152 assertions), including all 8 concurrency tests, the full migration rollback test and the server check |
| **MySQL 8.4.13 LTS** (local, see below) | `composer test:mysql` | ✅ **821 passed** (10152 assertions), including all 8 multi-process concurrency tests, the full migration rollback test (now with the Phase 08 and 09 migrations) and the server check; run on 2026-11-12 with the server confirmed as 8.4.13 on port 3307 |
| MySQL 8.4 in GitHub Actions | `.github/workflows/backend-tests.yml`, job `mysql84` | ⚠️ not run: every run of this private repository still ends in `startup_failure` with zero jobs, which points to an account-level Actions setting (minutes, spending limit or verification), not to the workflow |

### Correction (Phase 09)

The MySQL 8.4 results reported at the end of Phases 07 and 08 did not come
from MySQL 8.4. Their runner script started the 3307 server but did not load
its credentials, so `composer test:mysql` read `backend/.env` and connected to
XAMPP's MariaDB on 3306 with the MySQL driver.

- Phase 08 (731 passed): certain; the MySQL 8.4 test database never received
  the Phase 08 migrations.
- Phase 07 (663 passed): the log of that same runner, so almost certainly
  MariaDB.
- Phase 05 and a Phase 06 run on 8 November did reach MySQL 8.4 (its test
  database holds the Phase 06 migrations); the final Phase 06 figure (640)
  cannot be traced to a run that did.

The first real MySQL 8.4 run since then (Phase 09) failed only three new
Phase 09 tests, which compared audit-log JSON objects in key order (point 2
below); every test from Phases 06 to 08 passed. To prevent a repeat:

- `composer test:mysql` and `composer test:mariadb` declare the server they
  are for (`DORY_DATABASE_SERVER`), and
  `tests/Feature/Database/DatabaseServerTest.php` fails the run when the
  suite reached another server ("reached 10.4.32-MariaDB");
- load the credentials as shown in [The local test server](#the-local-test-server)
  every time.

Migrations on MySQL 8.4: `migrate:fresh` → `migrate:rollback --step=6` (the
six queue migrations) → `migrate` → `migrate:refresh` → `migrate:reset` →
`migrate`, all successful after the fix below. After rolling back to the
Phase 02 state, the `businesses` table had exactly its Phase 02 indexes and
foreign key (`businesses_created_by_foreign`, `ON DELETE SET NULL`).

## What the MySQL 8.4 run found

1. **A migration that could not be rolled back** (fixed). The `down()` of
   `2026_11_05_200200_add_profile_fields_to_businesses_table` failed with
   error 1553, `Cannot drop index
   'businesses_created_by_idempotency_key_unique': needed in a foreign key
   constraint`. When the unique index `(created_by, idempotency_key)` was
   added, the server silently dropped the index it had created for the
   `created_by` foreign key, because the new index can serve that key. The
   foreign key then depends on the unique index. **MariaDB 10.4 fails the
   same way**: it had not been noticed because only the latest batch had
   been rolled back there; SQLite is not affected. The `down()` now drops the
   foreign key first and re-creates it at the end. `up()` is unchanged, so
   existing databases (including the local `dory_db`) are not affected.
   The new `tests/Feature/Database/MigrationRollbackTest.php` rolls back
   every migration and runs them again on every database; with the old
   `down()` it fails on MySQL 8.4 and on MariaDB.
2. **JSON object key order.** MySQL's `JSON` type stores objects in a
   normalized form (keys ordered by length, then bytes), so
   `{"from": …, "to": …}` is read back as `{"to": …, "from": …}`. MariaDB
   stores JSON as text and keeps the order. The application only reads JSON
   values by key, so nothing changes for it; one test compared whole objects
   in order and now sorts the keys first (values and types are still compared
   strictly). JSON arrays keep their order in both engines.
3. **`composer test:mysql` and `composer test:mariadb` stopped after 300
   seconds**, Composer's default script timeout, before the suite finished.
   Both scripts now disable that timeout (as the `dev` script already did).

Nothing else needed a change: row locks, the counter upsert, the nullable
unique "key" columns, idempotency and the eight concurrency races behaved
exactly as on MariaDB.

## A MariaDB difference found later

MariaDB 10.4 has `explicit_defaults_for_timestamp` off, so it adds
`ON UPDATE CURRENT_TIMESTAMP` to the first NOT NULL `TIMESTAMP` of a table.
MySQL 8.4 has it on and never did this. Passing on one engine did not reveal the
problem on the other: it was found in Phase 05, by looking at a real ticket. See
[QUEUE_DATABASE_SCHEMA.md](QUEUE_DATABASE_SCHEMA.md#timestamps-that-must-not-move).

It came back in Phase 08: the billing tables' required timestamps
(`issued_at`, `expires_at`, `received_at`, `occurred_at`) had no default.
MariaDB refused to create the invoices table (a later NOT NULL timestamp gets an
invalid zero default), and would have made the others update themselves. SQLite
accepted all of it; the MariaDB run caught it. Every new NOT NULL timestamp
therefore uses `->useCurrent()`, and `TimestampColumnsTest` checks the whole
schema on MySQL and MariaDB.

## The local test server

MySQL Community Server **8.4.13 LTS**, official Windows ZIP
(`mysql-8.4.13-winx64.zip`, MD5 checked against the value published by
Oracle; `mysqld.exe` signed by Oracle America, Inc.). It is a plain process,
**not a Windows service**, listens on **127.0.0.1:3307 only**, and is
completely separate from XAMPP's MariaDB on port 3306. Server defaults: InnoDB,
`REPEATABLE-READ`, the default strict `sql_mode`, and `caching_sha2_password`
authentication (MySQL 8.4's default; PHP 8.4's `pdo_mysql` connects without
any change).

| Path (outside the repository) | Content |
|---|---|
| `D:\mysql84\mysql-8.4.13-winx64` | the program |
| `D:\mysql84-data` | its data (only `dory_db_testing`) |
| `D:\mysql84-config\my.ini` | configuration; `mysqld.err` is its log |
| `D:\mysql84-config\credentials.env` | random passwords for `root` and for `dory_test`, which has rights on `dory_db_testing` only |
| `D:\mysql84-download` | the downloaded ZIP |

Start it (PowerShell):

```powershell
Start-Process -WindowStyle Hidden D:\mysql84\mysql-8.4.13-winx64\bin\mysqld.exe -ArgumentList '--defaults-file=D:/mysql84-config/my.ini'
```

Run the suite against it. The first line loads `DB_HOST`, `DB_PORT`,
`DB_USERNAME` and `DB_PASSWORD` from the credentials file into this
PowerShell window only, so no password is typed; they override `.env`,
which stays unchanged. Close the window afterwards so they do not affect
anything else started from it.

```powershell
Get-Content D:\mysql84-config\credentials.env | Where-Object { $_ -match '^(DB_\w+)=(.*)$' } | ForEach-Object { Set-Item "env:$($Matches[1])" $Matches[2] }
cd C:\Users\SOLAV\Desktop\Dory\dory_app\backend
D:\php84\php.exe D:\composer\composer.phar test:mysql
```

Stop it (asks for `MYSQL84_ROOT_PASSWORD` from the credentials file):

```powershell
D:\mysql84\mysql-8.4.13-winx64\bin\mysqladmin.exe -h127.0.0.1 -P3307 -uroot -p shutdown
```

To remove it completely, stop it and delete the four `D:\mysql84*` folders.
Nothing else was installed: no service, no `PATH` change, no registry
entries.

## GitHub Actions

The `mysql84` job (service `mysql:8.4`) runs the same migrations and suite on
every push once Actions starts for this repository. Check *Settings → Billing
and plans* (Actions minutes and spending limit for private repositories) and
*Settings → Actions → General*, then re-run the workflow.

## Why it works

- InnoDB row locks (`SELECT … FOR UPDATE`) and REPEATABLE READ behave the same
  in MySQL 8.4 and MariaDB 10.4 for the patterns used (lock the session row
  first, then read).
- The counter uses `INSERT … ON DUPLICATE KEY UPDATE last_number =
  last_number + 1, updated_at = ?` (Laravel `upsert` with every updated column
  given explicitly), so it does not use `VALUES()`, which is deprecated in
  MySQL 8.0.20+.
- Every foreign key references a primary key, as MySQL 8.4's
  `restrict_fk_on_non_standard_key` (on by default) requires.
- Uniqueness relies on nullable unique columns (multiple NULLs allowed in
  both engines), not on partial indexes (which MySQL lacks).
- No `JSON_*` functions in queries; `json` columns are only stored and read,
  by key.
- Dates are plain `DATE` values computed in PHP in the branch's time zone, so
  the server time zone does not matter.

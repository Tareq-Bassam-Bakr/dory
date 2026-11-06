# MySQL 8.4 compatibility

Production targets MySQL 8.x; local development uses XAMPP's MariaDB 10.4.

## Status (Phase 04, 2026-11-06)

| Database | How | Result |
|---|---|---|
| SQLite (in memory) | `composer test` | ✅ 461 passed, 8 skipped (the multi-process concurrency tests, which need MySQL/MariaDB) |
| MariaDB 10.4 (XAMPP) | `composer test:mariadb` | ✅ 469 passed, including all 8 concurrency tests; migrations run fresh, the latest batch (which holds all six queue migrations) rolled back, then migrated again |
| **MySQL 8.4** | GitHub Actions workflow `.github/workflows/backend-tests.yml` (job `mysql84`, service `mysql:8.4`) | ❌ **not run yet.** MySQL 8.4 is not installed on the development machine, and GitHub did not start any Actions job for this repository (`startup_failure` with zero jobs, even for a one-line `echo` probe workflow), which points to an account-level Actions setting (for private repositories: Actions minutes, spending limit or account verification), not to the workflow or the code. |

**MySQL 8.4 compatibility is therefore not proven.** It must not be claimed
until the `mysql84` job (or a local MySQL 8.4) passes.

## How to get the MySQL 8.4 result

Either:

1. **GitHub Actions**: check *Settings → Billing and plans* (Actions minutes
   and spending limit for private repositories) and *Settings → Actions →
   General* on the repository, then re-run the workflow
   (*Actions → Backend tests → Run workflow*). The `mysql84` job prints the
   server version, runs migrations fresh/rollback/fresh and the whole suite,
   including the concurrency tests.
2. **Locally**: install MySQL 8.4 (for example the official ZIP from
   dev.mysql.com on another port), create `dory_db_testing`, and run
   `D:\php84\php.exe D:\composer\composer.phar test:mysql` with the MySQL
   credentials in `.env`.

## Why it is expected to work

The schema and queries avoid engine-specific behavior:

- InnoDB row locks (`SELECT … FOR UPDATE`) and REPEATABLE READ behave the same
  in MySQL 8.4 and MariaDB 10.4 for the patterns used (lock the session row
  first, then read).
- The counter uses `INSERT … ON DUPLICATE KEY UPDATE last_number =
  last_number + 1` (Laravel `upsert`). It does not rely on `VALUES()` for the
  increment (deprecated in MySQL 8.0.20+; the generated statement may still
  use it for other columns, which MySQL 8.4 accepts with a deprecation note).
- Every foreign key references a primary key, as MySQL 8.4's
  `restrict_fk_on_non_standard_key` (on by default) requires.
- Uniqueness relies on nullable unique columns (multiple NULLs allowed in
  both engines), not on partial indexes (which MySQL lacks).
- No `JSON_*` functions in queries; `json` columns are only stored and read.
- Dates are plain `DATE` values computed in PHP in the branch's time zone, so
  the server time zone does not matter.

These are reasons, not proof; the test run is what counts.

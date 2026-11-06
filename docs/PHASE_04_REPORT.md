# Phase 04 report: queue engine, digital tickets and live business operations

Date: 2026-11-06 · Baseline: tag `phase-03-baseline` → `5856a40` ·
Final commit: see [Commits](#commits).

## Summary

DORY now has a working, generic queue engine. Each service of a branch gets a
queue; managers configure it and open a session each day; customers join from
the app and guests from a QR code without an account; staff call customers in
FIFO order and serve them one at a time. Ticket numbers (`PREFIX-001`) are
allocated atomically, reset daily in the branch's time zone and are never
reused. Every rule that matters under load is enforced by the database
(locks and unique indexes) and was exercised with real parallel processes on
MariaDB. The business team runs everything from 12 new Flutter screens.

**MySQL 8.4** was verified afterwards, on 2026-11-07, on a local MySQL
8.4.13 server: the whole suite passes, including the concurrency tests. The
run found one migration that could not be rolled back (on MariaDB too),
which is fixed ([details](#mysql-84-verification-2026-11-07)). GitHub
Actions still does not start for account reasons. There is no real-time push
yet; screens poll and say so.

## Delivered, step by step

| Step | Result |
|---|---|
| A. Inspect | Phase 03 state confirmed: 329 backend tests (SQLite and MariaDB), 257 Flutter tests, analyzer clean, local = remote. |
| B. Git baseline | Annotated tag `phase-03-baseline` on `5856a40`, pushed. |
| C. Migrations | `queues`, `queue_sessions`, `queue_daily_counters`, `queue_tickets`, `queue_events`, `queue_staff_assignments` ([schema](QUEUE_DATABASE_SCHEMA.md)). |
| D. Configuration & sessions | Queue CRUD (one per service, unique prefix per branch, soft delete), open/pause/resume/close, closing policy, day roll-over ([lifecycle](QUEUE_LIFECYCLE.md)). |
| E. Atomic daily counters | Upsert + row lock inside the join transaction; unique `(queue, day, number)` ([numbering](TICKET_NUMBERING.md)). |
| F. Joining | Signed-in customers and guests; capacity, duplicates, paused/closed/inactive; Idempotency-Key; guest access token returned once, stored hashed ([guests](GUEST_JOINING.md)). |
| G. State machine & events | One state machine for every transition; append-only `queue_events`; `QueueActivity` dispatched after commit ([state machine](TICKET_STATE_MACHINE.md)). |
| H. Staff & call-next | Assignments; FIFO call-next under a session lock; call/start/complete/skip/no-show (after grace period)/recall; one customer per staff member. |
| I. Wait estimation | `WaitTimeEstimator` contract + deterministic historical estimator ([estimates](WAIT_TIME_ESTIMATION.md)). |
| J. Public link & guest page | Link + QR payload, token rotation, `/join/{token}` and `/ticket/{id}` web pages in English and Arabic. |
| K. Flutter | 12 screens (below), 154 new strings in English and Arabic. |
| L. Integration & concurrency tests | Feature tests for every endpoint; 8 multi-process concurrency tests ([strategy](CONCURRENCY_STRATEGY.md)). |
| M. All tests | See [Test results](#test-results). |
| N. MariaDB + MySQL 8.4 | Both pass, MySQL 8.4 on a local server; the CI workflow was added but GitHub does not start it ([status](MYSQL_84_COMPATIBILITY.md)). |
| O. Documentation | 11 queue documents, this report, README update. |
| P. Commits | Milestones committed under the owner's identity, pushed and verified. |

### Flutter screens (business **Queues** tab)

1. Business Queues Overview · 2. Create Queue · 3. Edit Queue Configuration ·
4. Queue Details · 5. Open Queue Session · 6. Active Queue Dashboard ·
7. Waiting Tickets List · 8. Currently Serving Tickets · 9. Ticket Details ·
10. Assign Employees · 11. Queue Session History · 12. Queue QR Code & Public
Link.

Live screens refresh every 15 s and show "Updates automatically every 15
seconds". QR codes are painted from the pure-Dart `qr` encoder (the
`qr_flutter` package was rejected because it imports
`package:flutter/material.dart`, which the app avoids).

### API changes from the plan

Documented in [QUEUE_API_REFERENCE.md](QUEUE_API_REFERENCE.md#changes-from-the-phase-04-plan):
added `GET /businesses/{business}/queues` and `GET /tickets/{ticket}`;
`POST /queues/{queue}/join` is the signed-in join while guests use
`/public/queues/{token}/join`; `GET /tickets/{ticket}/position` returns the
whole ticket; web pages added behind the QR code; queue payload includes its
business.

## Test results

Backend numbers are from the final runs on 2026-11-07, after the MySQL 8.4
fixes, on the development machine (on 2026-11-06, before them: SQLite 461
passed + 8 skipped, MariaDB 469 passed). Flutter numbers are from 2026-11-06.

| Suite | Result |
|---|---|
| Backend, SQLite (`composer test`) | ✅ **462 passed, 8 skipped** (4222 assertions). The 8 skipped are the multi-process concurrency tests, which SQLite cannot meaningfully run. |
| Backend, MariaDB 10.4 (`composer test:mariadb`) | ✅ **470 passed** (4413 assertions), including all 8 concurrency tests |
| Backend, MySQL 8.4.13 (`composer test:mysql`, local server) | ✅ **470 passed** (4413 assertions), including all 8 concurrency tests |
| Pint (code style) | ✅ passed |
| Flutter (`flutter test`) | ✅ **320 passed** (257 before + 63 new) |
| Flutter analyzer | ✅ no issues |

Phase 04 added 141 backend tests (329 → 470, of which 8 run only on
MySQL/MariaDB) and 63 Flutter tests,
including every queue screen in the English/Arabic layout smoke test on a
small phone and a tablet (overflow = failure). No existing test was removed or
weakened; `RouteProtectionTest`'s explicit list of unauthenticated routes was
extended with the four guest routes, plus a new check that guest ticket routes
require the ticket token. Two existing tests were corrected during the MySQL
8.4 verification without loosening them (see
[below](#mysql-84-verification-2026-11-07)).

### Concurrency evidence (MariaDB 10.4)

Up to 16 separate PHP processes, each with its own connection, released at the
same instant: 16 simultaneous joins got exactly 1…16; one customer racing
themselves got 1 ticket; 8 idempotent retries made 1 ticket; capacity 5 held
against 16 guests; 6 staff calling at once got 6 different customers in FIFO
order; one staff member calling 6 times got 1; 6 simultaneous opens made 1
session. A control test shows the harness really overlaps the processes (an
unsafe read-then-write counter loses updates under it).

### Checks beyond the test suites

- **Real API contract**: responses captured from the real backend were parsed
  by the Flutter repository code (temporary test, not committed): all fields
  matched.
- **Real browser, guest page** (local server, phone-sized viewport, Arabic and
  English): join → ticket page → leave the queue. This found **two bugs the
  test suite could not see**, both fixed in `4aff574`: Laravel 13's new
  `PreventRequestForgery` middleware was not excluded from the session-less
  guest forms, and `Origin: null` (sent by browsers on pages with
  `Referrer-Policy: no-referrer`) was wrongly rejected. Regression tests were
  added. Temporary demo data was removed afterwards.
- **Migrations on MariaDB**: fresh, then the latest batch (all six queue
  migrations) rolled back, then migrated again. A full rollback of every
  migration is now part of the test suite (`MigrationRollbackTest`).
- The new Flutter screens were **not** run on the phone in this phase; they
  are covered by widget tests against the fake API, the layout smoke test and
  the real-response contract check above.

## Database compatibility

| Database | Status |
|---|---|
| SQLite | ✅ tests (not used as concurrency evidence) |
| MariaDB 10.4 | ✅ full suite + concurrency tests + migrations |
| MySQL 8.4.13 LTS | ✅ full suite + concurrency tests + full migration rollback, on a local server (2026-11-07) |
| MySQL 8.4 in GitHub Actions | ⚠️ not run. The workflow `.github/workflows/backend-tests.yml` (job `mysql84`, service `mysql:8.4`) was pushed, but every run ended in `startup_failure` with **zero jobs**, including a one-line `echo` probe workflow on a temporary branch (deleted afterwards). That points to an account-level GitHub Actions setting for this private repository (Actions minutes, spending limit or account verification), not to the workflow. The first run also had a real YAML mistake (`"Server: "` in a plain scalar), fixed in `9bab93e`. |

[MYSQL_84_COMPATIBILITY.md](MYSQL_84_COMPATIBILITY.md) has the details, how
to start, use and remove the local server, and why the design works on both
engines.

### MySQL 8.4 verification (2026-11-07)

Because GitHub Actions does not start, MySQL Community Server 8.4.13 LTS was
installed locally from Oracle's official ZIP (checksum and signature
checked), as a plain process on `127.0.0.1:3307` with its own data folder
and a test user limited to `dory_db_testing`. XAMPP's MariaDB, `dory_db` and
`.env` were not touched (`dory_db` still has its 19 migrations and 11
categories). The run found:

1. **A migration that could not be rolled back** (also on MariaDB):
   `add_profile_fields_to_businesses_table` (Phase 03) failed in `down()`
   with error 1553, because the server had folded the `created_by` foreign
   key's index into the new unique `(created_by, idempotency_key)` index.
   `down()` now drops and re-creates that foreign key; `up()` is unchanged.
   New `MigrationRollbackTest` rolls back every migration on every database
   and was checked to fail with the old `down()` on MySQL 8.4 and MariaDB.
2. **JSON key order**: MySQL's `JSON` type does not keep object key order,
   so a `BusinessManagementTest` assertion comparing audit-log properties as
   ordered arrays failed. It now sorts keys before the same strict
   comparison. Application code reads JSON by key and is not affected.
3. **Composer timeout**: `composer test:mysql` / `test:mariadb` hit
   Composer's 300-second script limit; both now disable it.
4. Not MySQL-related, found during the run: `CustomerJoinTest`'s "numbering
   restarts on the next business day" depended on the real date (it failed on
   every database on 2026-11-07, Damascus time, because `setUp` opens a
   session at the real time). The test now closes that session first and
   opens both days itself, with one more assertion.

## Security highlights

- Staff routes resolve queues, sessions, tickets and assignments only through
  the user's memberships and branch access; anything else is a 404.
- Guest access tokens and idempotency keys are stored only as SHA-256 hashes;
  the public link token as a hash plus an encrypted copy; guest phone numbers
  encrypted; tokens never in URLs or logs; event metadata never holds personal
  data.
- Guest web forms are protected without sessions (fetch-metadata/origin
  checks, double-submit nonce, SameSite=Strict cookies).
- Rate limits for joins, public lookups and staff operations.

Details: [QUEUE_SECURITY.md](QUEUE_SECURITY.md).

## Commits

All Phase 04 commits are authored and committed by **Tareq Bassam Bakr
<255685054+Tareq-Bassam-Bakr@users.noreply.github.com>** (GitHub shows
`Tareq-Bassam-Bakr`), with **no co-author line**. Each push was verified
(`git rev-parse HEAD` = `gh api …/commits/main`). No force-push, no history
rewrite.

| Commit | Content |
|---|---|
| `9bcd8e6` | Queue schema, configuration and session lifecycle (D) |
| `56ab6bf` | Joining, guest access, wait estimates (E, F, I) |
| `c7b3d94` | Staff operations, assignments, dashboard (G, H) |
| `869ef83` | Guest web page (J) |
| `c599d25` | Flutter queue screens (K) |
| `8845e29` | Multi-process concurrency tests and MySQL 8.4 CI workflow (L, N) |
| `9bab93e` | CI workflow YAML fix |
| `4aff574` | Guest web forms fixed for real browsers |
| `962c56a` | Documentation and this report (O) |
| *(this commit)* | MySQL 8.4 verification: rollback fix, rollback test, test corrections, Composer timeout, documentation |

## Repository identity and historical attribution

- The repository uses the owner's identity (repository-local Git config, the
  GitHub noreply address of `Tareq-Bassam-Bakr`); no email was invented.
- No Claude or Anthropic attribution exists in project files. The only
  mentions are technical: the `.gitignore` rule for local AI-tool settings,
  and two documents recording that the Laravel skeleton's `CLAUDE.md` /
  `AGENTS.md` were removed in Phase 02.
- **Historical, reported separately, not changed:** the 9 commits from Phase
  01–03 (`f7bddce`, `c60fa11`, `20a301d`, `bace5bf`, `95f3848`, `e38a954`,
  `1d33cab`, `77f586d`, `5856a40`) carry a `Co-Authored-By: Claude …`
  trailer. Removing it would rewrite history and require a force-push, so it
  was left as is and needs your explicit decision.

## Limitations and open items

1. **MySQL 8.4 is verified locally only**: the CI job does not run until
   GitHub Actions starts for this repository (see above).
2. **No real-time updates.** Screens poll (app 15 s, guest page 20 s).
   `QueueActivity` is ready for Laravel Reverb in a later phase.
3. **Customer app experience**: customers can join through the API, but the
   app's own ticket screens (the customer **Tickets** tab) are a later phase,
   as planned.
4. **Guests**: "one active ticket per person" is enforced for signed-in
   customers; guests are limited per browser (cookie), by rate limits and by
   capacity.
5. **Estimates** are deterministic averages: managers serving without an
   assignment are not counted as servers, and time already spent with the
   current customer is not subtracted.
6. **Stale sessions** are closed when the queue is next opened (day
   roll-over); there is no nightly job yet.
7. **Priority queues** are prepared (column, ordering, index) but not
   implemented, as instructed.
8. The local development database (`dory_db`) now has the six queue tables
   (`php artisan migrate`).
9. GitHub Actions history contains the failed CI runs, including the probe
   run.

Not implemented, as instructed: discovery, maps, Reverb / real-time
broadcasting, AI/ML, payments, subscriptions, priority queues, enterprise
integrations.

## Next

Phase 05 has **not** been started and will not be without your explicit
approval.

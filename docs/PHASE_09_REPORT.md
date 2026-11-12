# Phase 09 report: platform admin dashboard and commercial readiness

Date: 2026-11-12 · Baseline: `a9f9238` (end of Phase 08) · Final commits:
see [Commits](#commits).

## Summary

DORY now has a **platform admin dashboard** at `/admin`: a server-rendered
part of the Laravel backend, separate from the Flutter app, in English and
Arabic (right to left), in DORY's blue and white with the approved icon. It
covers:

- platform statistics;
- business review: approve, suspend, reactivate;
- accounts: suspend, reactivate;
- subscriptions, plans and prices;
- payment records with a two-step approval;
- revenue from paid invoices only;
- the audit log;
- run-time settings.

Every action is authorized on the server, never by hiding buttons. Admins
are made on the server only (`php artisan dory:admin`); no page or endpoint
can make one. Sensitive operations go to an append-only platform audit log.

**Payments.** A payment received by bank transfer, cash or mobile wallet is
now recorded first and counts only after an admin approves it (optionally a
second admin: four-eyes approval). Recorded, gateway-verified and
admin-given plans are always shown apart, and revenue counts paid invoices
only. Live online payments are **off by default** and can only be switched
on in the server environment, after the bank test in the new
[gateway checklist](PAYMENT_GATEWAY_CHECKLIST.md). No credential was
invented, no merchant account opened, nothing charged.

**Nothing commercial changed.** Prices, limits, the trial and every
business's entitlements are exactly as in Phase 08. The open questions are
written up with recommendations in
[COMMERCIAL_DECISIONS.md](COMMERCIAL_DECISIONS.md), for your decision.

**A correction.** The MySQL 8.4 results reported for Phase 08 (and Phase 07)
did not come from MySQL 8.4: the runner started the server but connected to
MariaDB. Details and the fix are [below](#correction-mysql-84-results).

## Delivered

| Scope | Result |
|---|---|
| Sign-in and sessions | Own session guard and database-backed session; cookie for `/admin` only, encrypted, HttpOnly, SameSite=Strict, Secure on HTTPS; 30 minutes idle, 8 hours at most; new session id at sign-in, ended on the server at sign-out; same answer and timing for every failure; 5 attempts a minute per address and account, 20 per address; every request re-reads the admin's account before any lookup |
| Overview | Users, businesses, branches, queues; paid, complimentary, trial, overdue and ended subscriptions computed now; payments awaiting approval; revenue (6 months); businesses per plan; overdue accounts and trials ending within 3 days; new businesses per month |
| Businesses | Search (name, slug, ID, owner email or name) and filters; profile, owner, plan and usage, branches, team, payments, subscription history, admin activity; approve, suspend (with a reason), reactivate (restores the previous status) |
| Users | Search and filters by status and role; memberships and signed-in devices; suspend (signs out every device) and reactivate. Never your own account or another admin's |
| Subscriptions | Every business by state now (tabs), plan filter, search; give or extend a trial, give a plan (until a date or open-ended), cancel on the owner's behalf, undo a cancellation, end now |
| Plans | Create and edit: names and descriptions in both languages, explicit limits or *unlimited*, features, order, availability, monthly and yearly prices per accepted currency typed as decimals; prices are never deleted, only no longer offered |
| Payments | Record (bank transfer, cash, mobile wallet, other; card only from the gateway); approve or reject; four-eyes option; tabs for awaiting, paid, rejected, failed, all and online attempts; a printable invoice |
| Revenue | Per currency and month for 3 to 24 months, approved by admins and verified online apart, awaiting approval shown apart and never counted, revenue by plan |
| Audit log | Sign-ins (failed ones with the address masked), admin grants, businesses, accounts, plans, subscriptions, payments, settings; who, when, IP, details; filters; append-only |
| Settings | Plans in force, trial plan and days, one trial per owner, grace days, accepted and default currency, four-eyes approval, discovery needs approval; each with its server default and who changed it. Read-only server configuration, never showing a secret |
| Admin API | New: payment list, approve, reject; cancel and resume on the owner's behalf. Recording a payment now creates an open record awaiting approval. Prices and payments only in accepted currencies |
| Admin accounts | `php artisan dory:admin list`, `create` (password asked twice, hidden), `grant`, `revoke` (ends that admin's sessions at once); all audited |
| Design | Sidebar navigation, statistic tiles, server-drawn SVG charts with screen-reader tables, responsive tables, filters, status badges; Arabic right to left; a strict Content Security Policy with no inline code; phone width with a menu |
| Docs | [ADMIN_DASHBOARD.md](ADMIN_DASHBOARD.md), [PRODUCTION_READINESS.md](PRODUCTION_READINESS.md), [PAYMENT_GATEWAY_CHECKLIST.md](PAYMENT_GATEWAY_CHECKLIST.md), [COMMERCIAL_DECISIONS.md](COMMERCIAL_DECISIONS.md); SUBSCRIPTIONS, PAYMENTS, SECURITY, LOCAL_DEVELOPMENT_STARTUP, MYSQL_84_COMPATIBILITY and the README updated |
| Startup | `start_dory.bat` shows the dashboard address; `stop_dory.bat` unchanged (the dashboard runs inside DORY-API) |

## Main changed files

| Area | Files |
|---|---|
| Backend, new | `routes/admin.php`, `config/admin.php`, 2 migrations, `app/Http/Controllers/Admin/*`, `app/Http/Middleware/{ConfigureAdminSession,AuthenticateAdmin,SetAdminLocale,AdminResponseHeaders}.php`, `app/Services/Admin/*`, `app/Services/Platform/*`, `app/Console/Commands/ManagePlatformAdmins.php`, `app/Models/{PlatformAuditLog,PlatformSetting}.php`, `app/Enums/PlatformAuditAction.php`, `app/Exceptions/AdminActionException.php`, `app/Support/AdminFormat.php`, `app/Http/Controllers/Api/V1/Admin/BillingPaymentController.php`, `resources/views/admin/*`, `public/dory-admin/*`, `lang/{en,ar}/admin.php`, `lang/ar/validation.php` |
| Backend, changed | `AppServiceProvider` (gates, bindings, run-time settings, sidebar count), `bootstrap/app.php` (admin routes and middleware group), `BillingSubscriptionController`, `BillingPlanController`, `RecordPaymentRequest`, `SavePlanRequest`, `CheckoutService` (live switch), `EnsurePlatformAdmin`, `Business`, `BillingInvoice`, `User` (no remember-me column), `config/{auth,billing,dory}.php`, `lang/en/api.php`, `routes/api.php`, `.env.example`, `composer.json` |
| Tests | `tests/Feature/Admin/*` (12 files), `tests/Feature/Billing/AdminPaymentsApiTest.php`, `tests/Feature/Database/DatabaseServerTest.php`, `tests/Concerns/UsesAdminDashboard.php` |
| Scripts | `scripts/dev/start_dory.ps1` (dashboard address) |

## Test results

All on 2026-11-12, on this machine, on the final code.

| Suite | Result |
|---|---|
| Backend, MySQL 8.4.13 (`composer test:mysql`, local server on 3307) | ✅ **821 passed** (10152 assertions), including the 8 multi-process concurrency tests, the full migration rollback test (with both Phase 09 migrations) and the new server check; the runner confirmed MySQL 8.4.13 on port 3307 first |
| Backend, MariaDB 10.4 (`composer test:mariadb`) | ✅ **821 passed** (10152 assertions), the same |
| Backend, SQLite (`composer test`) | ✅ **811 passed, 10 skipped** (9958 assertions). The 10 need MySQL or MariaDB |
| Pint | ✅ passed |
| Flutter (`flutter test`) | ✅ **600 passed** (unchanged: this phase changes no Flutter code) |
| Flutter analyzer | ✅ no issues |
| `dart format` | ✅ no changes (259 files) |
| Real browser | Walk against the real API ([below](#checked-in-a-real-browser)) |
| `start_dory.bat` / `stop_dory.bat` | Both scripts parse. Not started end to end: the start would ask about the 2 pending migrations on `dory_db`, which is your decision |
| GitHub Actions | ⚠️ not run: it still does not start for this repository (account-level) |

Phase 09 added 90 backend tests (731 → 821). They cover:

- every admin route refused to guests, customers and business owners, with
  nothing changed (each page and action);
- the admin check running before any record lookup;
- sign-in, the cookie, throttling, idle and absolute timeouts, demotion,
  suspension and sign-out ending the session on the server;
- every business, account, plan, subscription, payment and settings action,
  including the refusals (own account, other admins, archived businesses,
  double approval, four-eyes);
- the audit log entries;
- revenue counting paid invoices only, apart by source;
- no inline script, style or event handler on six main pages (the Content
  Security Policy would refuse inline code on every page anyway);
- every page in English and Arabic;
- English and Arabic having the same keys and placeholders, and a label for
  every status, event, method and action.

No test was removed or weakened. Two existing test files changed, because
the behaviour changed on purpose:

- `AdminBillingApiTest`: recording a bank transfer now returns an open
  record; the test then approves it and checks the same plan, period and
  history as before. Creating a plan with SYP prices first adds SYP to the
  accepted currencies.
- `MastercardPaymentTest`: the tests switch live payments on for the faked
  gateway (they are off by default now), and a new test shows that with the
  switch off no checkout starts and nothing reaches the gateway, even with
  credentials set.

**Found by the tests and fixed:**

1. **Signing out of the dashboard crashed**: Laravel's session guard reads a
   "remember me" column the users table does not have (the API never had
   sessions). The User model now declares none.
2. **Flash messages and sign-in could use the wrong session store** if
   anything had resolved it before the dashboard configured its session; the
   dashboard now binds its own store explicitly.
3. **The session row right after sign-in had no admin id**, so revoking an
   admin could miss that session (the next request would still have refused
   them). The admin guard now applies from the sign-in page on.
4. **The admin API refused lowercase currency codes** (`usd`) after the new
   accepted-currency check; they are accepted again, as before.
5. An empty `ADMIN_SESSION_SECURE_COOKIE=` in `.env` would have made the
   cookie non-Secure even on HTTPS; empty now means "follow `APP_URL`".
6. On real MySQL 8.4, three new tests compared audit-log JSON objects in key
   order; MySQL reorders keys (documented since Phase 05). They now compare
   by key.

## Checked in a real browser

In the built-in browser, against the real API (`artisan serve` on port 8010)
on the throwaway database `dory_db_testing` (MariaDB), never `dory_db`. The
data (13 accounts, 7 businesses with branches and queues, paid, overdue,
complimentary, trial and ended subscriptions, payments awaiting approval and
rejected) was made through DORY's own services; the first admin was made
with `php artisan dory:admin grant`.

| Step | Result |
|---|---|
| `/admin` signed out | Sends you to the sign-in page; strict CSP, `no-store`, `noindex`; both cookies limited to `/admin`, SameSite=Strict |
| Wrong password | "These details do not match an active admin account."; email kept, password cleared |
| Overview | Every number matched the data: 13 users (1 suspended), 7 businesses, 14 queues, paid and complimentary, 2 trials, 1 overdue, 2 ended, 1 payment waiting, revenue 64.00 and 15.00 USD by month |
| Businesses | Search by owner email; the business page with the subscription computed now (overdue: paid until 12 Nov, grace until 19 Nov) |
| Cancel the overdue subscription | "Subscription canceled."; an overdue period ends now: Free, Ended, limits 1/1 |
| Approve a mobile wallet payment | Invoice `DORY-2026-000004`, paid until 12 Nov 2027; printable invoice; timeline with who recorded and who approved |
| Four-eyes on (Settings) | "1 setting saved", shown as changed by the admin; the recording admin's Approve button is disabled, and posting it anyway is refused: "Another admin must approve a payment you recorded." |
| Second admin (account language Arabic) | The dashboard opens in Arabic, right to left; approves the payment: invoice `DORY-2026-000006`, the trial's remaining days kept |
| Suspend a customer (Arabic reason) | Signed out everywhere; in the audit log with the reason |
| Plan editor | Pro yearly 150.00 → 144.00 saved and shown |
| Revenue | 244.00 USD from 5 approved payments, 0 verified online, 0 waiting; by plan 195.00 and 49.00 |
| Discovery needs approval | On: the public discovery API lists only the 2 approved businesses; off: all 6 active ones again |
| Forged requests | From a separate client with a valid admin session: no token, a wrong token, `Sec-Fetch-Site: cross-site` or `same-site` → **419**, nothing changed; the real form token works |
| Phone width (390 px) | Menu button and slide-in sidebar (no JavaScript); no page scrolls sideways; tables scroll inside their cards |
| Sign out | "You are signed out."; the pages send you to sign-in again |
| Server log | No errors |

**Found in the walk, and fixed with tests where it was logic:**

1. **The new-businesses chart showed money amounts** (64.00, 15.00) as its
   labels: a Blade include sees the page's variables, and the revenue
   chart's labels leaked into it. The bar heights were right. Every chart now
   gets all its values explicitly (the new test fails without the fix).
2. **The plans list showed 0 businesses on every plan** (the edit page said
   4): the counts were looked up by id instead of code. Fixed (the new test
   fails without the fix).
3. **An invoice showed the day the money arrived as its issue date**; it is
   now the day it was numbered (on approval), and the payment date stays in
   "Paid by … on …".
4. A complimentary plan with an end date showed no date in the
   subscriptions list.
5. Disabled buttons looked enabled; Arabic pages put left-to-right values
   (IDs, phones, the buyer's email) far from their labels; invoice numbers
   broke over three lines and the invoice table was cramped on a phone; the
   Arabic "paid by" line read awkwardly; checkbox help text was bold.

**Found when reviewing the final code:** when a session ended (timeout,
access removed) or an admin signed out, the message and the sign-in page
fell back to English even for an admin working in Arabic. The language is now
set before the admin check and kept after the session ends (the new test
fails without the fix).

## Payment safety

- `BILLING_ONLINE_PAYMENTS=false` by default: every checkout is refused even
  with a provider and credentials set (tested). Only the server environment
  can change it; the dashboard shows it read-only.
- Payments received outside DORY are recorded, then approved; card payments
  cannot be entered by hand.
- Revenue counts paid invoices only, with recorded-and-approved and
  verified-online apart; the invoice says which.
- [PAYMENT_GATEWAY_CHECKLIST.md](PAYMENT_GATEWAY_CHECKLIST.md): credentials
  handling, configuration, 12 test cases with the bank's test cards, refunds,
  sign-off, the switch to go live and to stop at once, reconciliation.

## Correction: MySQL 8.4 results

The MySQL 8.4 results in the Phase 07 and Phase 08 reports did not come from
MySQL 8.4. Their runner script started the local MySQL 8.4 server but did
not load its credentials, so `composer test:mysql` used `backend/.env` and
connected to MariaDB on port 3306 with the MySQL driver.

- **Phase 08: certain.** The MySQL 8.4 test database never received the
  three Phase 08 migrations.
- **Phase 07:** its reported figure (663 passed) is the log of that same
  runner, so almost certainly MariaDB too.
- **Earlier:** the Phase 05 runs and a Phase 06 run on 8 November did reach
  MySQL 8.4 (its test database holds the Phase 06 migrations). The final
  Phase 06 figure (640) cannot be traced to a run that reached it.

The first real MySQL 8.4 run since then (this phase) failed only 3 new
Phase 09 tests (JSON key order, fixed above); everything from Phases 06 to 08
passed. Two things prevent a repeat:

- `composer test:mysql` and `test:mariadb` now declare their server, and
  `DatabaseServerTest` fails the suite when it reached another one (it fails
  with the old runner: "reached 10.4.32-MariaDB").
- The new runner loads the credentials and prints the server version before
  running.

[MYSQL_84_COMPATIBILITY.md](MYSQL_84_COMPATIBILITY.md) and the three earlier
reports carry this note.

## Applying the migrations to dory_db

Done on 2026-11-12 after your approval ("Yes, with a backup"), in this order.

1. **Connection:** Laravel's default connection `mariadb` on 127.0.0.1:3306,
   database `dory_db`, MariaDB 10.4.32: XAMPP's server, untouched. No `DB_*`
   variable overrode `backend/.env`. Every command ran with the cache in
   memory, so booting the app wrote nothing to `dory_db` on the side.
2. **Before:** 36 tables and 85 rows: your 1 test account with its 3 sign-in
   tokens and 1 notification preference, 11 categories, the 3 plans with 4
   prices, 32 cache rows and 30 migration records. No businesses.
3. **Pending:** exactly the two Phase 09 migrations. None of their tables or
   columns existed. `migrate --pretend` showed only new tables, indexes,
   foreign keys and nullable columns: nothing dropped, updated or deleted.
4. **Backup**, outside the repository:
   - file: `D:\dory-backups\dory_db-before-phase09-20261112-145104.sql`;
   - made with XAMPP's `mysqldump`, `--single-transaction`, routines,
     triggers and events;
   - 66,781 bytes: 36 tables, ending in "Dump completed";
   - SHA-256 `B79DC2B8EA3489055C039C07BB392E7E4E8CF02EFE32B47E49F7000764C9F30D`.

   To restore it: `D:\xampp\mysql\bin\mysql.exe -u root < "<file>"`. This
   replaces the current `dory_db`.
5. **Run:** `php artisan migrate` (the normal command, nothing else). Both
   reported DONE, in batch 7.
6. **After:**
   - `migrate:status`: 32 run, 0 pending;
   - all 36 existing tables are present with identical row counts (only the
     two migration records were added);
   - `admin_sessions`, `platform_audit_logs` and `platform_settings` exist,
     empty, with their indexes and foreign keys;
   - the 5 new `businesses` columns and the 3 new `billing_invoices`
     columns exist and are all empty;
   - no column in `dory_db` updates itself (no `ON UPDATE`);
     `platform_audit_logs.created_at` defaults to the current time.
7. **API and dashboard on `dory_db`** (read only, cache and sessions kept in
   memory for the check):
   - the health check answers;
   - `GET /api/v1/billing/plans` returns the three plans with unchanged
     limits and prices, and online payment off;
   - discovery answers (no businesses yet);
   - the dashboard's sign-in page answers with its security headers, and
     signed-out pages redirect to it;
   - the row counts were the same afterwards.

## Decisions for you

1. **Several Free businesses under one owner**: recommendation, one automatic
   Free business per owner, more by admin approval (needs code).
2. **Prices and currency**: USD as the reference; a local currency only at
   fixed prices an admin reviews; amounts from pilot conversations.
3. **Trial and limits**: keep 14 days and the current limits until the pilot
   shows otherwise.
4. **Free plan policy**: free per business without a time limit; customers
   never pay.
5. **Invoice numbering**: numbers are unique but not continuous (they are
   record ids). If the seller's country requires one continuous sequence per
   year, a dedicated sequence is needed.

Details: [COMMERCIAL_DECISIONS.md](COMMERCIAL_DECISIONS.md),
[PRODUCTION_READINESS.md](PRODUCTION_READINESS.md#11-payment-and-invoice-legal-requirements).

## Required actions

1. ~~Approve applying the 2 Phase 09 migrations to `dory_db`~~: **done**
   with your approval, after a backup
   ([Applying the migrations](#applying-the-migrations-to-dory_db)).
   `start_dory.bat` now finds no pending migrations.
2. Make the first admin on your machine (it asks for the password,
   hidden). `dory_db` has no admin yet:

   ```bash
   php artisan dory:admin create you@example.com --name="Your Name"
   ```

3. The decisions above.
4. Before launch, the short list in
   [PRODUCTION_READINESS.md](PRODUCTION_READINESS.md#short-list-before-launch),
   above all **account deletion in the app** (required by the App Store and
   Google Play) and the legal texts.
5. GitHub Actions still does not start for this repository (account-level).

## Limitations

1. The dashboard's statistics compute every subscription's state on request:
   fine for thousands of businesses, not for hundreds of thousands
   ([ADMIN_DASHBOARD.md](ADMIN_DASHBOARD.md#scale)).
2. No refunds from DORY; no PDF invoices (the invoice page prints); invoices
   are not yet tax invoices.
3. Admin accounts are managed from the command line only (by design); there
   is no admin role hierarchy: every platform admin can do everything in the
   dashboard.
4. No account deletion and no retention clean-up yet (production readiness
   items).
5. iOS was not built or checked (it needs a Mac).

## Commits

All commits use the owner's identity with no co-author line, and every push
was verified. The repository remains private. No paid service was activated.

| Commit | Content |
|---|---|
| `cb9c347` | Backend: admin dashboard, platform services, billing administration API, migrations, 90 tests |
| `ce64ed9` | Documentation, this report, the MySQL 8.4 correction in earlier reports, `start_dory` address |
| *(this commit)* | Phase 09 migrations applied to `dory_db`, with a backup; report updated |

## Next

Phase 10 has **not** been started and will not be without your explicit
approval. Suggested first, whatever Phase 10 becomes: your first admin
account, account deletion in the app, and your answers on the commercial
decisions.

## What was touched

- `dory_db`: only the two Phase 09 migrations, applied after your approval
  and a backup ([below](#applying-the-migrations-to-dory_db)). No seed, no
  account, no data. XAMPP's configuration: not touched.
- `dory_db_testing` (MariaDB): reset by the test suite, then filled with the
  walk data; reset again by the final suite.
- The local MySQL 8.4 test server (3307): started for its runs and stopped
  afterwards.
- No paid service, no deployment, no merchant account, no real charge.

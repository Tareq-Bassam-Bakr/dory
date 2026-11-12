# Phase 08 report: subscriptions and monetization

Date: 2026-11-11 · Baseline: tag `phase-07-baseline-for-08` → `13a1467` ·
Final commit: see [Commits](#commits).

## Summary

Businesses now have plans. **Free**, **Pro** and **Business** limit what a
business can add (branches, queues, team seats) and unlock insights; new
businesses try Pro for 14 days. The server enforces every limit. **Customers
never pay**: no customer request checks a plan, and a business over its
limit keeps serving customers and running every queue it has.

Plans, prices, currencies, billing periods and limits are data, managed by
platform admins through an admin API. The full lifecycle is built:

- trial, activation, renewal, upgrade with credit for unused time;
- downgrade at the end of the period, cancellation and resumption;
- grace period, expiry, failed payments;
- the history of every change, and numbered billing records.

**Payments:** a payment received by bank transfer, cash or mobile wallet is
recorded by an admin, and the plan starts at once. **Online card payment is
built but not live**: Mastercard Gateway through a Syrian acquiring bank,
the one suitable option found for Syria. It is tested against a faked
gateway only. No payment account, credential or paid service was created or
assumed, and nothing was charged.

The 3 new migrations were applied to `dory_db` after your approval, with a
backup first ([below](#applying-the-migrations-to-dory_db)).

## Delivered

| Scope | Result |
|---|---|
| Plans | Free (1 branch, 3 queues, 3 seats), Pro (3, 15, 10, insights), Business (unlimited, insights per branch). The limits follow DORY's features ([SUBSCRIPTIONS.md](SUBSCRIPTIONS.md#why-these-limits)). Prices are placeholders: 15 and 49 USD a month, 150 and 490 a year |
| Configurable | Plans and prices in the database, defaults in `config/billing.php`; trial plan and length, grace days, currency, payment link lifetime and an on/off switch in `.env` |
| Lifecycle | Trial (once per owner), activation, renewal from the end of the period, upgrade now with credit, downgrade scheduled for the end of the period, cancel and resume, past due with 7 grace days, expiry to Free. Applied on every request (`SubscriptionResolver`) and stored every 15 minutes (`billing:sweep`) |
| Entitlements | One service, on the server: branch, queue and seat limits when adding (with a row lock against parallel requests), insights features. Over-limit businesses keep everything working |
| Roles | The subscription belongs to the business. Owner: everything. Manager: sees plan and usage. Employee: nothing. Other businesses: 404 |
| History and billing records | `subscription_events` for every change; `billing_invoices` numbered `DORY-2026-000042` |
| Admin API | Plans and prices; subscriptions by status; record a payment; extend a trial; assign or end a plan |
| Payments | `PaymentGateway` contract; Mastercard Gateway Hosted Checkout; the server verifies every payment with the bank (amount and currency); webhooks with secret check and de-duplication; signed, expiring payment page; late payments applied, mismatches refused ([PAYMENTS.md](PAYMENTS.md)) |
| Flutter | Plan and billing, plans with comparison and monthly or yearly, upgrade and downgrade, billing history, trial, renewal and expiry status, a prompt at every limit, insights locked state, overview tile and notice. English and Arabic, DORY's design system and colours, app icon unchanged |
| Provider evaluation | Stripe, Paddle and Polar exclude Syria. Mastercard Gateway launched in Syria in 2026. Recommendation and sources in [PAYMENTS.md](PAYMENTS.md#choosing-a-provider) |
| Startup | `start_dory.bat` opens a DORY-SCHEDULER window; `stop_dory.bat` stops it with the others |

## Main changed files

| Area | Files |
|---|---|
| Backend, new | `config/billing.php`, 3 migrations, `app/Enums/{BillingPeriod,SubscriptionStatus,PlanLimit,PlanFeature,InvoiceStatus,CheckoutStatus,PaymentMethod,SubscriptionEventType}.php`, 7 models, `app/Services/Billing/*`, `app/Services/Payments/*`, `app/Contracts/PaymentGateway.php`, `app/Http/Controllers/Api/V1/{Billing,Admin}/*`, `app/Http/Controllers/Web/BillingCheckoutPageController.php`, `app/Http/Middleware/EnsurePlatformAdmin.php`, `app/Http/Requests/Billing/*`, `app/Http/Resources/Billing/*`, `app/Console/Commands/BillingSweep.php`, `resources/views/billing/*`, `lang/{en,ar}/billing.php`, `app/Support/Money.php`, `app/Exceptions/BillingException.php` |
| Backend, changed | `BusinessService` (starts the subscription), `BranchService`, `QueueService`, `InvitationService` (limits), `BusinessIntelligenceController` (features), `BusinessAbilities`, `BusinessPolicy`, `ApiResponse` (`details`), `AppServiceProvider`, `bootstrap/app.php`, routes, `config/dory.php`, `lang/en/api.php`, `.env.example` |
| Flutter, new | `lib/features/subscriptions/*` |
| Flutter, changed | Error model (`details`), failure messages, routes, business permissions, overview, branch, queue and invitation forms, invitation page, insights page and providers, ARB files |
| Scripts | `scripts/dev/start_dory.ps1` (scheduler window) |
| Docs | New: [SUBSCRIPTIONS.md](SUBSCRIPTIONS.md), [PAYMENTS.md](PAYMENTS.md), this report. Updated: README, API_CONVENTIONS, LOCAL_DEVELOPMENT_STARTUP, MYSQL_84_COMPATIBILITY |

## Test results

All on 2026-11-11, on this machine, on the final code.

| Suite | Result |
|---|---|
| Backend, MySQL 8.4.13 (`composer test:mysql`, local) | ⚠️ **Not run on MySQL 8.4** (correction, Phase 09): the 731 passed were on MariaDB, because the runner did not load the MySQL 8.4 credentials. The real MySQL 8.4 run in Phase 09 passed every Phase 08 test ([PHASE_09_REPORT.md](PHASE_09_REPORT.md#correction-mysql-84-results)) |
| Backend, MariaDB 10.4 (`composer test:mariadb`) | ✅ **731 passed** (6553 assertions), the same |
| Backend, SQLite (`composer test`) | ✅ **722 passed, 9 skipped** (6361 assertions). The 9 need MySQL or MariaDB |
| Pint | ✅ passed |
| Flutter (`flutter test`) | ✅ **600 passed** |
| Flutter analyzer | ✅ no issues |
| `dart format` | ✅ no changes |
| Real app | Browser walk against the real API ([below](#checked-in-the-real-app)) |
| On-device tests (`integration_test`) | Not rerun in this phase |
| `start_dory.bat` / `stop_dory.bat` | Both scripts parse; the new DORY-SCHEDULER window is recorded and stopped like the others. Not started end to end: a start would ask about the 3 pending migrations on `dory_db`, which is your decision |
| GitHub Actions | ⚠️ not run: it still does not start for this repository (account-level) |

Phase 08 added 68 backend tests (663 → 731) and 16 Flutter tests (584 →
600). They cover:

- every transition;
- the limits, including a business over its limit that keeps operating and
  serving customers;
- tenant isolation for owners, managers, employees, other businesses and
  admins;
- the admin API;
- payments against a faked gateway: success, decline, amount mismatch, late
  capture, webhook secret, duplicates, the bank unreachable.

No test was removed or weakened. Four existing tests changed, each because
the behaviour changed on purpose:

- `BusinessIntelligenceApiTest`: two tests use `?branch=`, now a Business
  plan feature; they put the business on Business first.
- `BusinessCreationTest`: the permissions list gained `view_subscription`
  and `manage_billing`.
- `RouteProtectionTest`: the public routes gained the plan list and the
  payment webhook.
- `QueueConcurrencyTest`: when it empties the database between tests, it
  now keeps the plan tables, which every real database has from the
  migration. Without them a new business has no plan to start on (found by
  the MariaDB run).

## Checked in the real app

In a real browser (Flutter web build at phone size, 390×844) against the
real API on the throwaway database `dory_db_testing` (MariaDB), never
`dory_db`. The phone was not used, because that needs you at the device;
the screens are the same Flutter code.

| Step | Result |
|---|---|
| New business | The overview shows "Pro trial: 14 days left" and "Plan and billing · Plan: Pro · Free trial" |
| Plan and billing | Trial end, days left and usage match the API: branches 1 of 3, queues 0 of 15, seats 1 of 10 |
| Plans | Monthly or yearly with "Save 16%", a card per plan, the comparison table |
| Choosing Business without a provider | "Pay by transfer or cash" with the right amount; no payment started |
| Ending the trial ("Choose Free") | Free at once: branches 1 of 1, queues 0 of 3, seats 1 of 3. Nothing deleted |
| A third invitation on Free (two pending) | Refused by the server; the form shows the number and the "Plan limit reached" dialog leads to the plans |
| Insights on Free | "Not included in your plan" with the plans, not an error; one request, no retries |
| An admin records a bank transfer (Pro, yearly) | Invoice `DORY-2026-000033`, paid until 11 Nov 2027; the history names the owner and the admin. The owner gets 403 from the admin API |
| After the payment | Pro, Active, "150.00 USD / year", "Paid until Nov 11, 2027", and the billing history line |
| Arabic | Right to left throughout; amounts stay readable (`150.00 USD سنويًا`) |
| Payment pages | The return page in Arabic; the signed payment link says online payment is not available; a changed or missing signature is refused (403); `no-store`, no framing, no referrer |

**Found this way, and fixed with tests:**

1. **MariaDB could not create the invoices table**, and three other
   timestamps would have changed themselves on every update. For example, a
   payment link would have expired the moment its session was saved. MariaDB
   gives the first required timestamp of a table an implicit
   `ON UPDATE CURRENT_TIMESTAMP`, and later ones an invalid zero default; the
   same trap as Phase 05. SQLite cannot show it; the MariaDB run did. The
   columns now have explicit defaults, and the existing
   `TimestampColumnsTest` guards all of them.
2. **Plan and billing showed a stale plan.** The overview keeps the
   subscription loaded while the business is open, so a payment recorded by
   an admin did not show. The plan pages now ask the server when they open
   (the new test fails without the fix).
3. **Refused requests were retried in the background.** Riverpod retries
   failed loads by default, so a Free business opening insights would have
   kept asking (and used up its rate limit). Insights, peak times and the
   billing screens now use the app's "no automatic retry" rule, like the
   other screens.
4. A payment that can no longer apply was flagged again on every status
   check. Now it is flagged once.
5. A person accepting an invitation to a full business saw "Upgrade to add
   more", which only the owner can do. They now get their own message.
6. The API said "up to 1 branches". The messages are now singular or plural
   as needed.

The web build used for the walk predates fixes 2 and 5; the page was
reloaded to see the payment, and both fixes are covered by widget tests.

## Decisions for you

1. **Prices and currency.** The plans carry placeholder prices in USD.
   Decide the real amounts, and whether to charge in USD, SYP or both
   (a plan can have prices in several currencies). They are set through the
   admin API, without a deployment.
2. **Online payment.** Whether to open a merchant account with a Syrian
   acquiring bank that offers Mastercard Gateway. Until then, payments are
   recorded by an admin.
3. **The limits and the trial.** The proposed limits, Pro for 14 days once
   per owner, and 7 grace days. All of them can be changed.
4. **Businesses created before Phase 08** start on Free. An admin can give
   them a trial or a plan instead. `dory_db` has no businesses yet, so this
   affects none today.
5. **Launch timing.** `BILLING_ENABLED=false` gives every business
   everything until you are ready to charge.
6. **Who the platform admins are.** The flag is set in the database only; no
   endpoint can grant it.
7. **Legal texts:** terms of sale, refund policy, and whether invoices need
   company and tax details.

## Required actions

1. ~~Approve applying the 3 Phase 08 migrations to `dory_db`~~: **done**
   with your approval, after a backup
   ([Applying the migrations](#applying-the-migrations-to-dory_db)).
   `start_dory.bat` now finds no pending migrations.
2. For online payment (when you decide to): the bank's merchant account and
   its credentials, entered by you in `backend/.env` on the server only
   ([PAYMENTS.md](PAYMENTS.md#setting-up-mastercard-gateway)).
3. GitHub Actions still does not start for this repository (account-level
   billing or settings). Until it does, MySQL 8.4 is verified on the local
   server only.

## Limitations

1. **No automatic renewal.** Each period is paid again; charging a saved card
   needs tokenization with the bank.
2. **Online payment is not verified against a real gateway**, and the bank's
   exact field names for its API version must be checked during its test
   integration.
3. **Invoices are records, not tax invoices**, and have no PDF.
4. **No refunds or proration on cancellation.** Upgrade credit needs the same
   currency.
5. **The admin API has no screens.**
6. **An owner can create several businesses**, each with its own Free plan:
   plans are per business. The trial is once per owner.
7. iOS was not built or checked (it needs a Mac).

## Applying the migrations to dory_db

Done on 2026-11-11 after your approval ("Yes, with a backup"), in this order.

1. **Connection:** Laravel's default connection was `mariadb` on
   127.0.0.1:3306, database `dory_db` (`SELECT DATABASE()`), MariaDB
   10.4.32: XAMPP's server, untouched. No `DB_*` variable overrode
   `backend/.env`.
2. **Before:** 29 tables and 75 rows. That is the 11 categories, your 1 test
   account with its 3 sign-in tokens and 1 notification preference, the
   cache and 27 migration records. There are no businesses yet.
3. **Pending:** exactly the three Phase 08 migrations. None of their 7 tables
   existed. They only create tables and write the default plans. No existing
   table is altered and nothing is updated or deleted.
4. **Backup**, outside the repository:
   - file: `D:\dory-backups\dory_db-before-phase08-20261111-202940.sql`;
   - made with XAMPP's `mysqldump`, `--single-transaction`, routines,
     triggers and events;
   - 51,537 bytes: 29 tables, 6 tables of data, ending in "Dump completed";
   - SHA-256 `96342CCE5A54E24F38D8CA68194605F6F6B802C1C99754080FA5C7F480EEC520`.

   To restore it: `D:\xampp\mysql\bin\mysql.exe -u root < "<file>"`. This
   replaces the current `dory_db`.
5. **Run:** `php artisan migrate` (the normal command, nothing else). All
   three reported DONE, in batch 6.
6. **After:**
   - `migrate:status`: 30 run, 0 pending;
   - all 29 existing tables are present, with identical row counts;
   - the 7 new tables exist with their indexes and foreign keys;
   - the plans are as configured: Free 1, 3, 3; Pro 3, 15, 10 with insights,
     15.00 and 150.00 USD; Business unlimited, 49.00 and 490.00 USD;
   - no column in `dory_db` updates itself (no `ON UPDATE`).
7. **API on `dory_db`** (read only):
   - the health check answers;
   - `GET /api/v1/billing/plans` returns the three plans in Arabic with
     their limits and prices, the 14-day Pro trial, 7 grace days and no
     online payment;
   - one run of `billing:sweep` reports nothing to change;
   - the row counts were the same afterwards.

## Local changes outside the repository

- `dory_db`: the 3 Phase 08 migrations, applied with your approval: 7 new
  tables and the 3 default plans with their 4 prices. No other change.
- The backup taken before them: `D:\dory-backups\dory_db-before-phase08-20261111-202940.sql`.
- `dory_db_testing` held the check's data; the final MariaDB run reset it, as
  every run does.
- The MySQL 8.4 test server was started for its runs and stopped afterwards.
  One run I interrupted ended it without a clean shutdown; it recovered on
  the next start, and the final run stopped it cleanly. Nothing listens on
  3307, 8000, 8010, 8080 or 8090.
- A web build of the app remains in `build/web` (ignored by git); the
  temporary `web/` folder was removed.
- Test accounts for the check were created only in `dory_db_testing`; their
  generated passwords stayed outside the repository.

## Commits

All commits use the owner's identity with no co-author line, and every push
was verified. The repository remains private. No paid service was activated.

| Commit | Content |
|---|---|
| `aadd0c0` | API: plans, subscriptions, entitlements, admin API, payments, scheduler, tests |
| `e46513b` | App: plan and billing screens, limit prompts; documentation and this report |
| *(this commit)* | Phase 08 migrations applied to `dory_db`, with a backup; report updated |

## Next

Phase 09 has **not** been started and will not be without your explicit
approval.

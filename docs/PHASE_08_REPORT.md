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

The 3 new migrations are **not** applied to `dory_db`: that needs your
approval ([Required actions](#required-actions)).

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
| Backend, MySQL 8.4.13 (`composer test:mysql`, local) | ✅ **731 passed** (6553 assertions), including the 8 multi-process concurrency tests and the full migration rollback test |
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
   them a trial or a plan instead.
5. **Launch timing.** `BILLING_ENABLED=false` gives every business
   everything until you are ready to charge.
6. **Who the platform admins are.** The flag is set in the database only; no
   endpoint can grant it.
7. **Legal texts:** terms of sale, refund policy, and whether invoices need
   company and tax details.

## Required actions

1. **Approve applying the 3 Phase 08 migrations to `dory_db`.** I would back
   it up first, as for Phase 06. Until they run, on `dory_db`:
   - creating a business, branch or queue, sending or accepting invitations,
     and insights fail;
   - customer access, discovery, joining and queue operations keep working.

   `start_dory.bat` will also offer to run them; please answer **no** there
   until you have decided, or let me do it with a backup.
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

## Local changes outside the repository

- `dory_db`: **unchanged**. No migration was applied and no data written.
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
| *(this commit)* | App: plan and billing screens, limit prompts; documentation and this report |

## Next

Phase 09 has **not** been started and will not be without your explicit
approval.

# Admin dashboard

The platform administration console at **`/admin`** (locally
`http://127.0.0.1:8000/admin`). It is a server-rendered part of the Laravel
backend, separate from the Flutter app: Blade pages, one stylesheet and one
small script in `backend/public/dory-admin/`, no build step and no
JavaScript framework. English and Arabic (right to left), in DORY's blue and
white, with the approved DORY icon.

Every page reads and changes data through the same services as the API
(`app/Services/Admin/*`, `app/Services/Billing/*`), and every request is
authorized on the server. Hiding a button is never the protection: the
tests post every action as a guest, a customer and a business owner and
check that nothing changes.

## Admin accounts

Only an **active account with `users.is_platform_admin`** can use the
dashboard and the admin API. No page or API endpoint can set that flag
(registration and profile requests that try are refused with 422, and the
column is not mass assignable). It is set on the server only:

```bash
php artisan dory:admin list
```

```bash
php artisan dory:admin create someone@example.com --name="Full Name"
```

```bash
php artisan dory:admin grant someone@example.com
```

```bash
php artisan dory:admin revoke someone@example.com
```

- `create` asks for the password twice (hidden) and applies the normal
  password policy. Nothing is written to the shell history.
- `grant` makes an existing, active account an admin. `revoke` removes the
  flag and ends that admin's dashboard sessions at once.
- Each is in the audit log, as done from the server command line.
- Admin accounts cannot be suspended from the dashboard, and no admin can
  suspend their own account there.

## Pages

| Page | What it does |
|---|---|
| **Overview** | Users, businesses, branches and queues; paid, complimentary, trial, overdue and ended subscriptions (computed now); payments awaiting approval; revenue for 6 months; businesses per plan; overdue accounts and trials ending within 3 days; new businesses per month |
| **Businesses** | Search by name, slug, ID, owner email or owner name; filter by status and review. A business page shows its profile, owner, plan in force and usage, branches with their queues, team, payments, subscription history and admin activity |
| **Users** | Search by name, email, phone or ID; filter by status and role (admin, business team, customer). An account page shows memberships, signed-in devices and activity |
| **Subscriptions** | Every business's plan as it stands now, in tabs by state, with plan and search filters |
| **Plans** | Plans with limits, features, prices and how many businesses are on each now. Create and edit |
| **Payments** | Awaiting approval, paid, rejected, failed, all, and online payment attempts. Record a payment; approve or reject; a printable invoice for paid payments |
| **Revenue** | Per currency and month, for 3 to 24 months: approved by admins and verified online, apart; payments awaiting approval shown apart and not counted; revenue by plan |
| **Audit log** | Sensitive operations with who, when, from which address, and the details; filters by action, admin, business, account and date |
| **Settings** | The run-time settings below, and a read-only view of the server configuration |

## Businesses

- **Approve** marks a business as reviewed by DORY (who and when are kept).
  It changes nothing for the business unless *Discovery needs approval* is
  on (Settings); then only approved businesses appear in discovery and
  search. Their queues keep working either way, and customers holding a
  queue's link can still join. It is off by default, so nothing changes for
  existing businesses until an admin turns it on.
- **Suspend** needs a reason. The team can no longer change the business or
  run its queues (`403 business_suspended`, the existing rule), and it
  disappears from discovery. Nothing is deleted.
- **Reactivate** restores the status it had before (active or inactive).
- An archived (deleted) business can be viewed but not suspended, and gets
  no new plan, trial or payment.

Each action is in the platform audit log and in the business's own history.

## Users

- **Suspend** needs a reason. The account is signed out on every device (its
  API tokens are deleted) and cannot sign in until reactivated. Memberships
  and records stay.
- **Reactivate** lets the person sign in again.
- Never your own account, never another admin's (gate
  `platform.manage-user`, checked again by the service).

## Billing

All changes go through the subscription lifecycle (docs/SUBSCRIPTIONS.md)
and are recorded with the admin as actor.

**Plans.** Names and descriptions in both languages, limits, features,
order, availability and prices.

- A limit is a number or explicitly *unlimited*; an empty field is an error,
  never unlimited by accident.
- Prices are typed as decimals (15.00, or 25000.500 for a three-decimal
  currency) per billing period and accepted currency. A price is never
  deleted, because invoices refer to it: it is no longer offered instead.
- A plan's code and whether it is free never change. The default plan is
  always offered.
- A change applies to every business on the plan at once. Lowering a limit
  removes nothing: a business above it just cannot add more.

**Subscriptions** (on the business page):

- give or extend a trial (1 to 90 days, not while a paid plan runs);
- give a plan without payment, until a date or open-ended (shown as
  *complimentary*, never as revenue);
- cancel on the owner's behalf (a paid plan stays until its period ends; a
  trial or overdue period ends now), undo a cancellation, or end the plan
  now.

**Payments received outside DORY** take two steps:

1. **Record** what arrived: business, plan, period, accepted currency,
   amount (the plan price by default), method (bank transfer, cash, mobile
   wallet, other), reference, date received, notes. Nothing changes yet.
   Card payments cannot be recorded by hand: they only come from the
   gateway. A reference can be recorded once.
2. **Approve** after checking the bank statement or cash book: the plan
   period starts (or renews, or upgrades with credit), the invoice gets its
   number (`DORY-2026-000042`), and the payment counts as revenue.
   **Reject** with a reason instead: nothing changes and it never counts.

With **four-eyes approval** on (Settings), the admin who recorded a payment
cannot approve it; another admin must. A payment is approved or rejected
once.

**Recorded and verified payments are never confused.** Every payment shows
its source: *recorded by an admin* (entered by hand), *verified online*
(confirmed by the server with the payment provider) or *given by an admin*
(no payment). Revenue counts paid invoices only, with the two kinds apart.
Online payments stay off by default (docs/PAYMENTS.md).

## Settings

Values admins may change while DORY runs. A stored value overrides its
default from `.env` for every request (`platform_settings`, cached); saving
the default again removes the override. Each change is in the audit log with
the old and new value.

| Setting | Default (`.env`) |
|---|---|
| Plans and limits in force | `BILLING_ENABLED` (true) |
| Trial plan, trial days, one trial per owner | `BILLING_TRIAL_PLAN` (pro), `BILLING_TRIAL_DAYS` (14), `BILLING_TRIAL_ONCE_PER_OWNER` (true) |
| Grace days | `BILLING_GRACE_DAYS` (7) |
| Accepted currencies, default currency | `BILLING_CURRENCIES` (USD), `BILLING_CURRENCY` (USD) |
| Four-eyes payment approval | `BILLING_FOUR_EYES` (false) |
| Discovery needs approval | `BUSINESS_APPROVAL_REQUIRED` (false) |

**Deliberately not settable here:** the payment provider, the live online
payments switch (`BILLING_ONLINE_PAYMENTS`), any credential, rate limits and
session settings. Those change in the server environment only. The page
shows them read-only: environment, debug mode, HTTPS, the admin cookie,
session limits, online payments, the provider and whether its credentials
are set (never their values), queue, cache, broadcasting, mail and log
channel.

## Audit log

`platform_audit_logs` records:

- admin sign-ins, failed sign-ins (with the address masked, `ad***@example.com`)
  and sign-outs;
- admin accounts granted, revoked and created from the command line;
- businesses approved, suspended and reactivated;
- accounts suspended and reactivated;
- plans created and changed (with what changed);
- trials, plans given, cancellations, resumptions and ends;
- payments recorded, approved and rejected;
- settings changed.

Each entry has the admin (or "server command line"), the business, the
record, the details, the IP address and the browser. Never passwords,
tokens or card data. Entries are only added: the dashboard has no way to
change or delete them, and the table has no `updated_at`.

## Security

- **Separate sign-in.** The dashboard has its own session guard (`admin`)
  and its own session, stored in the database (`admin_sessions`) with the
  admin's id. The API stays token-based and keeps no sessions.
- **The session cookie** `dory_admin_session` is sent to `/admin` only:
  encrypted, HttpOnly, SameSite=Strict, ends when the browser closes, and
  Secure when `APP_URL` is `https://` (or `ADMIN_SESSION_SECURE_COOKIE`).
- **Session limits:** signed out after 30 minutes without activity and after
  8 hours in any case (`ADMIN_SESSION_IDLE_MINUTES`,
  `ADMIN_SESSION_ABSOLUTE_HOURS`). The session id changes at sign-in;
  signing out ends the session on the server, so an old cookie opens
  nothing.
- **Every request** re-reads the admin's account: a revoked flag or a
  suspension ends access at the next request. This check runs before any
  route binding, so nothing is looked up for anyone else.
- **Sign-in:** the same answer, in about the same time, for a wrong
  password, an unknown address and an account that is not an admin. Limited
  to 5 attempts a minute per address and account and 20 per address
  (`ADMIN_LOGIN_PER_MINUTE`, `ADMIN_LOGIN_PER_IP_PER_MINUTE`).
- **Rate limit:** 120 dashboard requests a minute per admin
  (`ADMIN_REQUESTS_PER_MINUTE`).
- **CSRF** tokens on every form (Laravel's session-based protection).
- **Headers:** a strict Content Security Policy (scripts, styles, images and
  forms from the site only, no inline code, no framing), `Cache-Control:
  no-store`, `X-Robots-Tag: noindex`. The tests check that no page has an
  inline script, style or event handler.
- **Tenant isolation** is unchanged: the admin flag gives no rights inside a
  business through the business API (it gets 404 there); admins work through
  the dashboard and the admin API.

The session settings apply to the PHP process serving the request
(php-fpm or `artisan serve`). The dashboard is not meant for long-running
workers such as Octane.

## Languages and design

`?lang=en` or `?lang=ar` switches the language for the session; otherwise
the admin's account language, then the browser's. It is applied before the
admin check, and the sign-in page keeps it after a session ends. Arabic pages are right to
left (CSS logical properties throughout). Every text is in
`lang/en/admin.php` and `lang/ar/admin.php`, which a test keeps identical in
keys and placeholders; Arabic validation messages are in
`lang/ar/validation.php`.

Charts are drawn on the server as SVG, with a hidden table of the same
numbers for screen readers. Tables scroll sideways on narrow screens; below
960 px the sidebar becomes a menu. Invoices print without the navigation.

## Scale

Subscription states are computed at request time with the same resolver as
the API, so the overview and the subscriptions list are right even before
the scheduler stored a change. That reads every business's subscription on
those pages: fine for thousands of businesses. Far beyond that, the pages
should use the stored status (at most 15 minutes old) or a cached summary.

## Files

| Area | Files |
|---|---|
| Routes | `routes/admin.php` (loaded under `/admin` with the `admin` middleware group in `bootstrap/app.php`) |
| Controllers | `app/Http/Controllers/Admin/*` |
| Middleware | `ConfigureAdminSession`, `AuthenticateAdmin`, `SetAdminLocale`, `AdminResponseHeaders` |
| Services | `app/Services/Admin/{BusinessAdministration,UserAdministration,BillingAdministration,PlatformStatistics}.php`, `app/Services/Platform/{PlatformAuditLogger,PlatformSettings}.php` |
| Command | `app/Console/Commands/ManagePlatformAdmins.php` (`dory:admin`) |
| Views and assets | `resources/views/admin/*`, `public/dory-admin/*` |
| Data | `admin_sessions`, `platform_audit_logs`, `platform_settings`; review and suspension columns on `businesses`; `received_at`, `reviewed_by`, `reviewed_at` on `billing_invoices` |
| Config | `config/admin.php`, `config/billing.php`, `config/dory.php` |
| Tests | `tests/Feature/Admin/*`, `tests/Feature/Billing/AdminPaymentsApiTest.php` |

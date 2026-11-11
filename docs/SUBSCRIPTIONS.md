# Subscriptions

A **business** has a plan. Customers never do: finding businesses, joining
queues (signed in or as a guest), following a ticket, favorites and ratings
are free, whatever plan the business is on. The server never checks a plan
on any customer request.

Plans limit what a business can **add** (branches, queues, team seats) and
unlock business-side features (insights). They never stop anything that
already exists: a business above its limit keeps every branch, queue and
member working, and keeps serving customers. It just cannot add more.

Payments: [PAYMENTS.md](PAYMENTS.md).

## Plans

The defaults, set in `backend/config/billing.php`:

| | Free | Pro | Business |
|---|---|---|---|
| Branches | 1 | 3 | unlimited |
| Queues (all branches together) | 3 | 15 | unlimited |
| Team seats (owner, members and pending invitations) | 3 | 10 | unlimited |
| Queues, QR joining, guest tickets, live updates, notifications, discovery listing | ✓ | ✓ | ✓ |
| Insights and peak times (business-wide) | – | ✓ | ✓ |
| Insights for one branch on its own (`?branch=`) | – | – | ✓ |
| Price per month | free | 15.00 USD | 49.00 USD |
| Price per year | free | 150.00 USD | 490.00 USD |

New businesses get **Pro for 14 days**, once per owner: an owner's later
businesses start on Free.

**The prices are placeholders.** They are there so that every part of the
system works end to end. The owner sets real prices and currencies; see
[Configuration](#configuration).

### Why these limits

They follow what DORY can do today, and only limit things that cost the
business owner something to run (locations, parallel queues, staff), never
what customers do.

- **Not limited: customers, tickets, joins or notifications.** Limiting
  them would make customers pay for the business's plan, which the product
  rules out. Busy days must never lock customers out.
- **Branches** are the clearest measure of a business's size. One location
  covers the typical small business (barber, clinic, bakery). Pro covers a
  small chain; Business has no limit.
- **Queues** count across all branches. In DORY a queue belongs to a service
  (one queue per service), so three queues mean three services served at the
  same time at one place. Pro allows about five per branch.
- **Team seats** count the owner, active members and pending invitations.
  Counting invitations stops a business from passing the limit by sending
  many invitations at once. Three seats are an owner and two staff.
- **Insights** (Phase 07) are the one premium feature. They are computed from
  the business's own records and help a growing business; the basic queue
  numbers stay on every plan.
- **Insights for one branch** matter for businesses with several branches,
  hence Business. Managers of selected branches already see only their
  branches on Pro; they cannot pick one on its own.
- **Not gated:** live updates, notifications, QR codes, guest joining and
  discovery listing. Those are the customer experience, and a business on
  Free must give its customers the same experience as any other.

## Configuration

`backend/config/billing.php`, with these `.env` switches:

| Key | Default | Meaning |
|---|---|---|
| `BILLING_ENABLED` | `true` | `false`: every business gets everything without limits (records are still kept). For example before launch |
| `BILLING_DEFAULT_PLAN` | `free` | The plan of a business without a trial or paid plan in force |
| `BILLING_TRIAL_PLAN` / `BILLING_TRIAL_DAYS` | `pro` / `14` | The trial of new businesses. `0` days: no trials |
| `BILLING_TRIAL_ONCE_PER_OWNER` | `true` | An owner gets one trial, not one per business |
| `BILLING_GRACE_DAYS` | `7` | After a paid period ends unpaid, the plan stays this long |
| `BILLING_CURRENCY` | `USD` | Currency of checkouts that name none |
| `BILLING_CHECKOUT_EXPIRES_MINUTES` | `60` | How long a payment link works |
| `PAYMENT_PROVIDER` | `none` | Online payments ([PAYMENTS.md](PAYMENTS.md)) |

The plans in the config file are **defaults only**. The first migration
copies them into the database, and from then on the database is the source
of truth: platform admins change names, limits, features, prices and
currencies through the [admin API](#admin-api) without a deployment. A
plan's code and whether it is free never change. Prices are never deleted
(invoices refer to them); they are deactivated instead.

A plan can have prices in several currencies (for example USD and SYP) and
for monthly and yearly billing. `null` limits are unlimited.

## Lifecycle

```
             new business
                  │
        ┌─────────┴───────────┐
   trial (Pro, 14 days)     free
        │ trial ends            ▲
        └──────────► expired ───┤ (the Free plan applies)
                                │
  payment ──► active (paid until a date) ──── period ends, cancelled ──► expired
                  │  ▲                                                     ▲
                  │  └── paying again (renewal) ◄── past_due ── grace ends ┘
                  └────── period ends, unpaid ────►  (plan kept 7 days)
```

| Status | Meaning |
|---|---|
| `trialing` | The trial plan is in force until `trial_ends_at` |
| `active` | The free plan, or a paid plan with time left |
| `past_due` | A paid period ended without payment. The plan stays until `grace_ends_at` |
| `expired` | A trial or paid plan ended. The Free plan applies |

**Paid periods are prepaid.** A payment buys one month or one year. Nothing
is charged automatically: renewing means paying again. The owner sees the
date, and the app says it in plain words.

| Change | What happens |
|---|---|
| Payment for the plan in force | **Renewal.** The new period follows the current one, also when paid early or during the grace days |
| Payment for a bigger plan | **Upgrade, now.** The unused part of the current paid period becomes extra days on the new plan (same currency only) |
| Payment during a trial | The paid period starts when the trial ends: no trial day is lost |
| Smaller paid plan | **Scheduled** for the end of the paid period (`scheduled_plan`). Paid time is never cut short. Buying a smaller plan while a bigger one runs is refused (`use_downgrade`) |
| Free plan, or cancel | A paid plan stays until the end of its period, then the Free plan applies, with no grace days. A trial, or a period already in its grace days, ends now. Nothing is refunded |
| Resume | Undoes a cancellation or a scheduled smaller plan before the period ends |
| Failed payment | Recorded as a failed invoice. The subscription itself does not change: the period still ends on its date |

Nothing is ever deleted when a plan ends. The business keeps its branches,
queues, members and history, and can pay again at any time.

**Time is applied on every request, not only by the scheduler.**
`SubscriptionResolver` turns the stored record into its state at this
moment: a trial that ended a minute ago already gives the Free plan. The
scheduler (`billing:sweep`, every 15 minutes) then stores the same
transitions and their history. Plans are therefore never late, even when
the scheduler is not running.

**History:** every change is a row in `subscription_events` (trial started
or ended, activated, renewed, upgraded, downgrade scheduled or cancelled,
cancelled, resumed, past due, expired, payment failed, plan assigned, trial
extended), with the plans and statuses before and after, the acting user and
the details. Payments are `billing_invoices` rows numbered `DORY-2026-000042`.

## Entitlements

`App\Services\Billing\Entitlements` is the only place that answers "may this
business add one more?" and "does its plan include this feature?". Every
check runs on the server; the app only shows what the server decided.

| Action | Check | Refusal |
|---|---|---|
| Create a branch | branches | 403 `plan_limit_reached` |
| Create a queue | queues (whole business) | 403 `plan_limit_reached` |
| Invite a team member | seats, counting pending invitations | 403 `plan_limit_reached` |
| Accept an invitation | seats, active members only (the invitation's own seat is the one taken) | 403 `plan_limit_reached` |
| Insights, peak times | `insights` | 403 `feature_not_in_plan` |
| Insights with `?branch=` | `branch_insights` | 403 `feature_not_in_plan` |

- The check runs inside the transaction that adds the resource, with the
  business row locked, so two parallel requests cannot both take the last
  place.
- A business's first branch, created together with the business, counts
  against the plan like any other.
- Nothing is cached between requests: an admin's change of a plan applies to
  every business on it at the next request.
- `plan_limit_reached` carries the numbers, so the app can explain them:

```json
{
  "success": false,
  "message": "This plan includes 1 branch. Upgrade to add more",
  "code": "plan_limit_reached",
  "errors": null,
  "details": { "limit": "branches", "max": 1, "used": 1, "plan": "free" }
}
```

## Who may do what

The subscription belongs to the business, never to a person.

| | Owner | Manager | Employee | Platform admin |
|---|---|---|---|---|
| See the plan, limits and usage | ✓ | ✓ | – | admin API |
| See billing history | ✓ | – | – | admin API |
| Pay, change, cancel, resume | ✓ | – | – | admin API (assign, extend, expire, record payments) |

Non-members get `404 business_not_found`, as for every business resource. A
suspended business still shows its plan and history, but its owner cannot
change it (`403 business_suspended`). The platform admin flag gives no
rights inside a business; admins use the admin API. No endpoint can make
anyone a platform admin: the flag is set in the database only.

## Business API

All under `/api/v1`, with a bearer token unless marked public.

| Method and path | Who | Does |
|---|---|---|
| `GET /billing/plans` | public | Plans with limits, features and prices; trial, grace days, whether online payment is available |
| `GET /businesses/{id}/subscription` | owner, manager | Status, plan in force, plan on record, dates, limits, usage, features, permissions |
| `GET /businesses/{id}/invoices` | owner | Billing history, newest first (paid and failed), paginated |
| `POST /businesses/{id}/subscription/checkout` | owner | `{plan, billing_period, currency?}`. Starts an online payment: `201 {checkout, payment_url}`. `503 payments_unavailable` without a provider |
| `GET /businesses/{id}/subscription/checkouts/{checkout}` | owner | The payment's state, after asking the provider, and the subscription |
| `POST /businesses/{id}/subscription/downgrade` | owner | `{plan, billing_period?}`. A smaller plan from the end of the period, or Free |
| `POST /businesses/{id}/subscription/cancel` | owner | Ends at the end of the paid period, or ends a trial now |
| `POST /businesses/{id}/subscription/resume` | owner | Undoes a cancellation or scheduled change |

`GET /businesses/{id}` also gains `permissions.view_subscription` and
`permissions.manage_billing`.

Errors:

| HTTP | code | When |
|---|---|---|
| 403 | `plan_limit_reached` | Adding one more is above the plan's limit (`details`: `limit`, `max`, `used`, `plan`) |
| 403 | `feature_not_in_plan` | The plan does not include the feature (`details`: `feature`, `plan`) |
| 409 | `subscription_change_not_allowed` | `details.reason`: `nothing_to_cancel`, `already_canceled`, `nothing_to_resume`, `use_checkout` (bigger plans are bought), `use_downgrade` (smaller plans are scheduled), `managed_by_admin`, `same_plan`, `paid_plan_active`, `already_expired` |
| 422 | `plan_not_available` | Unknown or inactive plan, or no price for that period and currency |
| 503 | `payments_unavailable` | No online payment provider is set up |
| 502 | `payment_provider_error` | The provider could not be reached |
| 404 | `checkout_not_found` | Not a payment of this business |

## Admin API

Under `/api/v1/admin/billing`, for platform admins only
(`users.is_platform_admin`). Everyone else gets `403 forbidden` before any
record is looked up, so the answer is the same for a business that does not
exist.

| Method and path | Does |
|---|---|
| `GET /plans` | Every plan, with inactive plans and prices |
| `POST /plans` | New plan: `code`, `is_free`, `name_en`, `name_ar`, `description_*`, `max_branches`, `max_queues`, `max_members` (required, `null` = unlimited), `features`, `sort_order`, `prices[]` |
| `PUT /plans/{code}` | Change names, limits, features, order, active flag; add or change prices (`{billing_period, currency, amount_minor, is_active}`) |
| `GET /subscriptions?status=&plan=&search=` | Businesses with their state now (and the stored status) |
| `GET /businesses/{id}` | One business: state, usage, history, invoices, payment attempts |
| `POST /businesses/{id}/payments` | Record a payment received outside DORY: `{plan, billing_period, method, currency?, amount_minor?, reference?, paid_at?, notes?}`. `method`: `bank_transfer`, `cash`, `mobile_wallet`, `card`, `other`. A reference can be recorded once |
| `POST /businesses/{id}/trial` | `{days (1–90), plan?}`: extend or restart a trial (not while a paid plan runs) |
| `POST /businesses/{id}/plan` | `{plan, until?, note?}`: give a plan without payment. With `until`, it ends then without grace days; without, it stays until an admin changes it |
| `POST /businesses/{id}/expire` | `{reason?}`: end the plan now (the Free plan applies) |

Example: recording a bank transfer for one year of Business.

```bash
curl -X POST "$API/admin/billing/businesses/$BUSINESS/payments" -H "Authorization: Bearer $ADMIN_TOKEN" -H "Content-Type: application/json" -d '{"plan":"business","billing_period":"yearly","method":"bank_transfer","reference":"TRX-2026-0042"}'
```

## Scheduler

`php artisan billing:sweep` runs every 15 minutes (`routes/console.php`). It
stores due transitions and settles payment links that expired. It is safe to
run any number of times, and does nothing before the billing tables exist.

- Locally, `start_dory.bat` opens it as the DORY-SCHEDULER window
  ([LOCAL_DEVELOPMENT_STARTUP.md](LOCAL_DEVELOPMENT_STARTUP.md)).
- In production: a cron entry running `php artisan schedule:run` every minute.

## Data

| Table | Holds |
|---|---|
| `subscription_plans` | Code, English and Arabic names and descriptions, limits, features, free or paid, active, order |
| `subscription_plan_prices` | Per plan: period, currency, amount in minor units (cents), active |
| `business_subscriptions` | One per business: plan, status, trial end, paid period, grace end, cancellation, scheduled plan, amount, provider |
| `subscription_events` | The history (see [Lifecycle](#lifecycle)) |
| `billing_invoices` | Paid and failed payments: number, plan, period, amount, method, provider reference, who recorded it |
| `payment_checkouts` | Online payment attempts ([PAYMENTS.md](PAYMENTS.md)) |
| `payment_webhook_events` | Provider notifications, once per notification id |

Amounts are integers in minor units (`1500` = 15.00), with the number of
decimals per currency (`App\Support\Money`).

**Businesses created before Phase 08** have no subscription record and are
on the Free plan. A platform admin can extend a trial or assign a plan to
them.

## In the app

- **Business overview:** "Plan and billing" (owners and managers) with the
  plan and status, and a notice when a trial is running out, a payment is
  due or a plan ended.
- **Plan and billing:** the plan, its dates in plain words, usage meters for
  branches, queues and seats, what the plan includes, and for the owner:
  plans, billing history, cancel or keep.
- **Plans:** monthly or yearly with the yearly saving, a card per plan, a
  comparison table, the trial and a reminder that customers never pay. The
  owner can choose a plan, pay for the next period, or switch to a smaller
  one at renewal. Without online payment, the app explains how to pay by
  transfer, cash or mobile wallet.
- **Billing history:** paid and failed payments with number, period and
  method.
- **At a limit:** creating a branch, a queue or an invitation over the limit
  shows the number, says that everything existing keeps working, and leads
  to the plans. Insights outside the plan show what the plans offer
  instead of an error.

All of it in English and Arabic (right to left).

## Limitations

1. **No automatic renewal.** Each period is paid by the owner, or recorded by
   an admin. Charging a saved card each month needs card storage at the
   bank (tokenization), a later step.
2. **Online payment is not live** ([PAYMENTS.md](PAYMENTS.md)).
3. **Invoices are payment records, not tax invoices.** Company details, tax
   and legal wording are not on them, and there is no PDF yet.
4. **No refunds or proration on cancellation.** Cancelling keeps the paid
   time until the end of the period.
5. **Upgrade credit needs the same currency.** Paying in another currency
   starts the new plan without credit.
6. **The admin API has no screens.** It is for support tools and scripts.

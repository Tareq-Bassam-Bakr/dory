# Phase 03 Report — Business Management & Flutter Authentication

**Status:** complete, pending your review. Phase 04 has not been started.
**Date:** 2026-11-05. **Repository:** https://github.com/Tareq-Bassam-Bakr/dory (private), branch `main`.

## 1. Git and GitHub

| Step | Result |
|---|---|
| Git initialized at `dory_app/` with a hardened `.gitignore` | ✅ (`.env`, keys, keystores, credentials files excluded) |
| Secret scan before the baseline | ✅ no tokens/keys; the real DB password and `APP_KEY` appear in no tracked file |
| Baseline commit (Phase 01 + 02, 104 + 116 tests passing) | ✅ `f7bddce` |
| Private repository created (your choice), baseline pushed | ✅ remote hash verified |
| One commit per tested milestone, each pushed and verified | ✅ see below; no force pushes |

| Commit | Milestone |
|---|---|
| `f7bddce` | Baseline: Phase 01 + Phase 02 |
| `c60fa11` | Categories and business management (+ Phase 03 schema) |
| `20a301d` | Branch management with branch-scoped access |
| `bace5bf` | Service management |
| `95f3848` | Team members and invitations (+ log-leak fix) |
| `e38a954` | Cross-tenant security test suite |
| `1d33cab` | Flutter authentication connected to the API |
| `77f586d` | Business onboarding and management screens |
| *final* | End-to-end test, documentation (this report) |

Pushes use the GitHub CLI credentials for that command only; your global git
configuration was not changed. The repo has a local commit identity
(`Tareq Bassam Bakr`, GitHub noreply address).

## 2. What was built

### Backend (Laravel 13)

- **Categories**: `business_categories` (en/ar names, stable slugs, icons,
  enable/disable, `parent_id` for subcategories) and an idempotent seeder
  with the 11 launch categories. Public `GET /business-categories`.
- **Businesses**: profile fields, owner-controlled active/inactive status,
  platform suspension (read-only), archiving delete, profile completion,
  statistics. Creation stores the business, the **owner membership**, an
  optional first branch and services in **one transaction**, with an
  optional `Idempotency-Key` against duplicate creation.
- **Branches**: full CRUD, WGS 84 (OpenStreetMap) coordinates validated as
  a pair, ISO country codes, time zones, activation, soft delete that
  archives the branch's services.
- **Services**: one generic model for every sector, per-branch unique
  names, duration, display order, activation, archiving.
- **Team**: branch-scoped memberships (`all` / `selected` branches), member
  listing, role and branch-access changes, removal; owner and self are
  protected; ownership cannot be granted or transferred.
- **Invitations**: hashed single-use tokens with expiry, one pending
  invitation per email (database-enforced), acceptance only by the invited
  account, decline, revoke, signed-out preview with masked email, delivery
  behind `InvitationNotifier` that reports honestly and never uses the log
  mailer, share links for manual sharing outside production.
- **Isolation**: tenant route bindings resolve every `{business}`,
  `{branch}`, `{service}`, `{member}`, `{invitation}` through the caller's
  memberships; unreachable resources are the same 404 as missing ones;
  `BusinessAbilities` holds the permission matrix; policies return
  specific codes (`business_suspended`).
- **Audit trail**: `business_audit_logs` for business and membership changes.
- 25 endpoints (22 from the brief + 3 additions needed by the team screen:
  change member, list invitations, revoke invitation).

### Flutter

- **Authentication on the real API** (Clean Architecture): login,
  registration (phone optional), `/auth/me`, logout; token in
  Keystore/Keychain-backed secure storage; session restored on start;
  expired or revoked tokens dropped; any 401 ends the session; guarded
  routes with safe `?from=` return.
- **15 business screens** on the real API: My businesses, Create business,
  Onboarding (5 steps), Business overview, Edit profile, Branch list,
  Create branch, Edit branch, Branch details, Services list, Create
  service, Edit service, Team members, Invite employee, Invitation details.
- Categories everywhere come from the API (the hardcoded enum was removed).
- `dory://app/invitations/<token>` deep links on Android and iOS, plus
  paste-a-link.
- 214 new strings in English and Arabic; every screen checked in both
  directions on phone and tablet sizes.

## 3. Database changes

Seven additive migrations (`2026_11_05_200100` … `200700`); Phase 02
migrations untouched. New tables: `business_categories`, `branches`,
`services`, `business_member_branch`, `business_invitations`,
`business_audit_logs`. Extended: `businesses` (category, contact,
timezone, logo/cover paths, idempotency key, soft delete),
`business_members` (`public_id` backfilled, `branch_access`). Applied to the
local `dory_db`; categories seeded. Details:
[DATABASE_FOUNDATION.md](DATABASE_FOUNDATION.md).

## 4. Test results (actual runs)

| Suite | Result |
|---|---|
| Backend, SQLite | ✅ **329 passed** (2,624 assertions) — 104 Phase 02 + 225 new |
| Backend, MariaDB 10.4 (`dory_db_testing`) | ✅ **329 passed** |
| Pint | ✅ |
| `composer audit` | ✅ no advisories |
| Config + route caching | ✅ (34 API routes) |
| Flutter analyze | ✅ no issues |
| Flutter tests | ✅ **257 passed** — 116 Phase 01 + 141 new |
| On-device end-to-end (TECNO KM7, Android 15, Arabic) against the running API | ✅ `integration_test/phase03_e2e_test.dart`: register → onboarding (business, branch with coordinates, service) → fresh app start restored the session from secure storage (`/auth/me`) into the business workspace |
| Real process restart on the phone | ✅ normal debug app: login, `am force-stop` (process gone), cold launch → session restored, "My businesses" shown without login |

New backend tests: business creation, categories, management, branches,
services, invitations (creation, acceptance, expiry, single use, email
match, delivery honesty), team management (roles, ownership protection,
self-promotion), and `tests/Feature/Security`: **all 20 tenant endpoints
attacked from another tenant** (same 404 as missing, no data changed),
smuggled ids, branch-manager boundaries, route protection, rate limits,
secrets in logs.

New Flutter tests: the real repository and interceptors over a fake HTTP
transport (login success/failure, registration validation, token
restoration incl. expired/revoked/offline, logout, 401 ending the session),
redirect rules and open-redirect protection, login/registration screens,
onboarding navigation and submission, form validation, branch and service
screen states (loading/empty/error/retry/permissions), team and invitation
screens, and the layout smoke test across all 23 screens in both languages.

No existing test was deleted or weakened. Tests that encoded Phase 01
placeholder behaviour now assert the intended Phase 03 behaviour (the
business workspace requires sign-in), with new tests for the guest case.

## 5. Security findings fixed during the phase

1. **Passwords could reach the logs (Phase 02 issue).** The local
   `php.ini` records argument values in stack traces, so a server error
   during login would log `AuthService->login('…', '<password>', …)`
   (PHP keeps up to 15 characters of each string argument).
   `bootstrap/app.php` now forces `zend.exception_ignore_args`. Two tests
   prove it: they **fail without the fix** and pass with it (password at
   login, invitation token during lookup).
2. Coordinates: `sometimes` let a lone latitude skip the "both or
   neither" check; fixed and covered.
3. Account suspension is now checked right after authentication, before
   any tenant lookup.
4. Found on the phone: login/registration responses carried no
   memberships, so business owners first landed on the customer home.
   They now return the same user shape as `/auth/me` (test added).

Device-test accounts and businesses were removed from `dory_db` afterwards.
During the device check, Android's Google Password Manager offered to save
the test password; it was declined ("Not now").

## 6. How to run

```powershell
cd backend
```

```powershell
D:\php84\php.exe artisan migrate
```

```powershell
D:\php84\php.exe artisan db:seed --class=BusinessCategorySeeder
```

```powershell
D:\php84\php.exe artisan serve
```

With the phone on USB:

```powershell
adb reverse tcp:8000 tcp:8000
```

```bash
flutter run -d <device-id>
```

```bash
flutter test integration_test/phase03_e2e_test.dart -d <device-id>
```

Tests: `D:\php84\php.exe D:\composer\composer.phar test`, `… test:mariadb`,
`flutter test`.

## 7. Unresolved items and limits

- **Email delivery is still not configured.** Invitations work through
  share links; nothing claims an email was sent. Configure a provider and
  `INVITATION_SEND_EMAILS=true`, then require verified emails for
  accepting invitations.
- **Redis** is still not installed locally (database cache/queue fallback).
- **MySQL 8** itself was not tested (MariaDB 10.4 and SQLite were); run
  `composer test:mysql` on staging.
- **Logo and cover uploads**: columns exist, no upload endpoint yet.
- **Platform administration** (suspending businesses, support views) has
  no API yet; suspension is enforced but can only be set in the database.
- **Maps**: coordinates are entered as numbers; map display and address
  search (OpenStreetMap/Nominatim, with their usage policies) come later.
- **Service name uniqueness** is checked in validation, not by a database
  constraint (soft-deleted names must be reusable); a rare race could save
  duplicates.
- **API messages** are English; the app shows its own localized text based
  on error codes.
- **Web-server access logs** must exclude `/api/v1/invitations/*` in
  production (nginx example in MEMBERSHIP_AND_INVITATIONS.md).
- **Localized defaults**: the suggested first-branch name is filled in the
  UI language at entry time (an Arabic UI stores "الفرع الرئيسي"); that is
  intended, and the owner can change it.

## 8. Documentation

New: [API_ENDPOINTS_PHASE_03.md](API_ENDPOINTS_PHASE_03.md),
[BUSINESS_MANAGEMENT.md](BUSINESS_MANAGEMENT.md),
[BUSINESS_ONBOARDING.md](BUSINESS_ONBOARDING.md),
[BRANCH_MANAGEMENT.md](BRANCH_MANAGEMENT.md),
[SERVICE_MANAGEMENT.md](SERVICE_MANAGEMENT.md),
[MEMBERSHIP_AND_INVITATIONS.md](MEMBERSHIP_AND_INVITATIONS.md),
[FLUTTER_API_INTEGRATION.md](FLUTTER_API_INTEGRATION.md), this report.
Updated: README, PROJECT_OVERVIEW, ARCHITECTURE, FOLDER_STRUCTURE,
MULTI_TENANCY (Phase 03 permission matrix), DATABASE_FOUNDATION, SECURITY,
LOCAL_BACKEND_SETUP.

## 9. Recommendation for Phase 04

The queue engine on top of branches and services: queues per service,
ticket issuing and numbering, the employee "call next" flow, customer
joining, and Laravel Reverb for live updates. Before starting, decide:
(1) queue scope per service vs per branch with service selection;
(2) whether guests may join without an account; (3) how a ticket number is
displayed (prefix per service, daily reset).

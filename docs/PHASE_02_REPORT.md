# Phase 02 Report: Backend Foundation, Authentication & Multi-Tenancy

**Date:** 2026-11-05 · **Status:** complete, awaiting approval for Phase 03.

## 1. Versions (verified)

| Component | Version |
|---|---|
| Laravel | **13.40.0** (`php artisan about`) |
| PHP | **8.4.27** NTS x64 (`D:\php84`; XAMPP's 8.2.12 is untouched) |
| Laravel Sanctum | 4.3.3 |
| PHPUnit | 12.5.43 |
| Laravel Pint | 1.27 |
| Composer | 2.9.5 |
| Database | MariaDB 10.4.32 (XAMPP) locally; SQLite 3.53 for tests |

## 2. Environment findings and decisions

| Finding | Action |
|---|---|
| PHP 8.2.12 < Laravel 13's minimum (8.3) | Stopped, reported, and with approval installed the official PHP 8.4.27 (SHA-256 verified) side by side. The framework was not downgraded. |
| Database is MariaDB 10.4 (XAMPP), not MySQL | Supported by Laravel 13. Used locally with `DB_CONNECTION=mariadb`. MySQL 8.4 recommended for production. |
| Redis not installed (no server, no client, no WSL/Docker) | Documented fallback (database cache and queues). The health check reports Redis honestly. Install options documented. |
| Flaky network (resets, DNS failures) | Composer retried with reduced parallelism; PHP download resumed and verified. |
| Skeleton `AGENTS.md`/`CLAUDE.md` told AI agents to run a remote PowerShell installer (`iex … php.new`) and install `laravel/boost` | **Not followed.** The files were removed from the project. |

## 3. Created and changed files

**New Laravel application:** `backend/` (Laravel 13 skeleton, then customised).

Application code (`backend/app/`):

- `Enums/`: `UserStatus`, `BusinessStatus`, `BusinessRole`, `MembershipStatus`, `AppLocale`
- `Exceptions/`: `ApiException`, `InvalidCredentialsException`, `AccountInactiveException`, `BusinessNotFoundException`, `DuplicateMembershipException`, `ApiExceptionRenderer`
- `Http/Controllers/Api/V1/`: `Auth/AuthController`, `Auth/PasswordResetController`, `Users/ProfileController`, `System/HealthController`
- `Http/Middleware/`: `AssignRequestId`, `ForceJsonResponse`, `SetSecurityHeaders`, `EnsureUserIsActive`, `ResolveBusinessContext`
- `Http/Requests/`: `Auth/{Register,Login,ForgotPassword,ResetPassword}Request`, `Users/{UpdateProfile,UpdateLocale}Request`, `Concerns/GuardsAccountInput`
- `Http/Resources/`: `UserResource`, `BusinessResource`, `BusinessMembershipResource`, `AuthTokenResource`
- `Models/`: `User` (rewritten), `Business`, `BusinessMember`
- `Policies/BusinessPolicy`
- `Services/`: `Auth/{AuthService,PasswordResetService,AuthResult}`, `Business/{BusinessMembershipService,CurrentBusinessContext}`, `Users/ProfileService`, `System/HealthCheckService`
- `Support/`: `ApiResponse`, `Pagination`
- `Providers/AppServiceProvider` (rate limits, password policy, strict models, reset URL)

Configuration and wiring: `bootstrap/app.php`, `routes/api.php`, `routes/web.php`,
`routes/console.php`, `config/dory.php` (new), `config/sanctum.php`,
`config/cors.php`, `config/logging.php` (mail channel), `lang/en/api.php`,
`.env.example`, `phpunit.xml`, `composer.json`.

Database: migrations `create_users_table` (rewritten),
`create_businesses_table`, `create_business_members_table`, Sanctum's
`create_personal_access_tokens_table`. Factories `User` (rewritten),
`Business`, `BusinessMember`. Seeders `DatabaseSeeder` (rewritten) and
`DemoDataSeeder`.

Tests (`backend/tests/`): `TestCase` and 13 test classes (see §6).

Removed from the skeleton: Vite/npm tooling (`package.json`,
`vite.config.js`, `.npmrc`, `resources/css`, `resources/js`, the welcome
view), the example tests, `AGENTS.md`, `CLAUDE.md`, and the dev packages
`laravel/pail` and `laravel/pao`.

Repository root: `README.md` (backend sections), `analysis_options.yaml`
(Dart analyzer excludes `backend/**`), and `docs/`: `BACKEND_ARCHITECTURE.md`,
`DATABASE_FOUNDATION.md`, `API_AUTHENTICATION.md`, `API_CONVENTIONS.md`,
`MULTI_TENANCY.md`, `SECURITY.md`, `LOCAL_BACKEND_SETUP.md`, and this report.

Outside the repository: `D:\php84` (PHP 8.4.27 and its `php.ini`), plus
MariaDB databases `dory_db` and `dory_db_testing` with the user `dory_app`.

## 4. Database tables

`users`, `businesses`, `business_members`, `personal_access_tokens`,
`password_reset_tokens`, `cache`, `cache_locks`, `jobs`, `job_batches`,
`failed_jobs`, `migrations` (11 tables, verified with `php artisan db:show`
on MariaDB). Details: [DATABASE_FOUNDATION.md](DATABASE_FOUNDATION.md).

## 5. Implemented endpoints

| Method | Path | Notes |
|---|---|---|
| GET | `/api/v1/health` | DB, cache and Redis checks; 503 when unhealthy |
| POST | `/api/v1/auth/register` | 201 + token |
| POST | `/api/v1/auth/login` | rate-limited, no enumeration |
| POST | `/api/v1/auth/logout` | revokes the current device token |
| GET | `/api/v1/auth/me` | user + memberships |
| POST | `/api/v1/auth/forgot-password` | always 200 |
| POST | `/api/v1/auth/reset-password` | revokes all tokens |
| PUT | `/api/v1/users/profile` | name / phone / timezone only |
| PUT | `/api/v1/users/locale` | `en` / `ar` |

The business foundation (models, relationships, membership service, request
context, middleware, policy) has no endpoints yet, by design.

## 6. Test results (real runs)

| Suite | Command | Result |
|---|---|---|
| Backend on SQLite | `composer test` | **104 passed, 573 assertions** |
| Backend on MariaDB 10.4 | `composer test:mariadb` | **104 passed, 573 assertions** |
| Code style | `vendor/bin/pint --test` | passed |
| Dependency audit | `composer audit` | no advisories |
| Config/route/event caching | `artisan optimize` path | all cacheable |
| End-to-end on real `dory_db` | `artisan serve` + curl | health ✓, register ✓, me ✓, logout ✓, revoked token 401 ✓, wrong password 401 ✓ (test user removed afterwards) |
| Logs after run | grep for passwords/tokens | none found |
| Flutter (Phase 01 regression) | `flutter analyze`, `flutter test` | no issues, **116 passed** |

Coverage by requirement:

- **Authentication:** registration success and validation, duplicate email
  (case-insensitive), login success and wrong password, unknown email
  identical to wrong password, suspended account, rate limiting, `me`,
  logout, revoked token rejected, per-device logout, expired token,
  suspended token, the full password reset flow, invalid reset token,
  validation.
- **Security:** self-promotion to platform admin blocked (registration and
  profile), protected profile fields (status, email, password, verification
  date, public id) rejected with no partial update, updates limited to the
  caller, unauthenticated requests rejected (also without an Accept header),
  no internal ids or password in responses, tokens stored hashed with the
  prefix.
- **Multi-tenancy:** cross-tenant access returns 404, identical to a
  non-existent business, smuggled business ids are ignored,
  inactive/invited memberships grant nothing, duplicates are blocked by the
  service and the database, memberships belong to the correct user, a user
  can own one business and work in another, the policy matrix, and platform
  admin independence.
- **Infrastructure:** health ok, health 503 when the DB is down and when
  Redis is configured but unavailable, migrations/columns/unique
  indexes/cascades, 404/405 envelopes, v1-only routing, request id
  generation and propagation, security headers, no error details with debug
  off.

## 7. Configuration still required from you

1. **PATH (optional):** put `D:\php84` before `D:\xampp\php` to use plain
   `php`/`composer`. Otherwise use the full paths in
   [LOCAL_BACKEND_SETUP.md](LOCAL_BACKEND_SETUP.md).
2. **Mail provider** (SMTP/SES/Postmark credentials) before password reset
   emails can actually be delivered. Until then they are only written to
   `storage/logs/mail-*.log`.
3. **`PASSWORD_RESET_URL`:** the Flutter deep link or web page for resets.
4. **Redis** (optional locally, required for production): choose an option
   from the setup guide.
5. **MySQL 8.4 test run** before production: `composer test:mysql`.
6. **Demo data** (optional): set `SEED_DEMO_PASSWORD`, then run
   `php artisan db:seed`.
7. **Production secrets** (APP_KEY, DB credentials, Redis URL) in your hosting
   provider's secret manager, never in the repository.

## 8. Unresolved issues and notes

- Redis is **not operational locally** (not installed). The fallback is in
  place, so the Redis path has only been tested for failure reporting.
- Email delivery is **not configured or tested**. The reset flow is verified
  with a faked notification.
- MySQL itself was not available, so compatibility was verified on MariaDB
  10.4 and SQLite. MariaDB 10.4 is past upstream end of life.
- API messages are English only. Arabic API and validation messages are a
  later task (clients localize by `code`).
- The project folder is still **not a git repository**. Recommended next:
  `git init` and a first commit (`.gitignore` files already exclude `.env`,
  `vendor/`, and build output).
- During setup, `winget search memurai --accept-source-agreements` was run to
  verify a package id. This accepted the winget source agreements for
  searching. No software was installed.

## 9. Recommendation for Phase 03

Business management on top of this foundation: business registration
(`createBusinessWithOwner`), business profile (CRUD under
`/businesses/{business}` with `business.context` + `BusinessPolicy`),
branches and services as tenant-scoped resources, member invitations, and
connecting the Flutter app's authentication screens to these endpoints
(secure token storage, Dio client, `code`-based error handling). Before
Phase 03 starts, decide on the primary sign-in method (email + password as
built, or phone + OTP).

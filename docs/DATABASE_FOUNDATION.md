# Database Foundation

Database: **`dory_db`** (utf8mb4 / utf8mb4_unicode_ci). The tables are created
by Laravel migrations in `backend/database/migrations/`.

## Identifiers

Every user-facing table has two keys:

- `id`: auto-increment `BIGINT`, used for joins and foreign keys. **Never
  returned by the API.**
- `public_id`: a ULID (`CHAR(26)`, unique, generated automatically by
  `HasUlids`). Returned as `id` in API responses and used in URLs.

ULIDs are sortable by creation time and not enumerable, so sequential ids
never leak business volumes or invite guessing.

## Tables

### `users`

| Column | Type | Notes |
|---|---|---|
| id | bigint PK | internal |
| public_id | char(26) unique | ULID |
| name | varchar(120) | |
| email | varchar(255) unique | stored lower-case |
| phone | varchar(20) nullable unique | E.164 (`+963912345678`); unique so it can become a login identifier |
| password | varchar(255) | bcrypt hash (`hashed` cast) |
| locale | varchar(10) default `en` | `en` or `ar` |
| timezone | varchar(64) default `UTC` | IANA name |
| status | varchar(20) default `active`, indexed | `active`, `suspended`, `deactivated` |
| is_platform_admin | boolean default false | platform-wide privilege; never mass assignable |
| email_verified_at | timestamp nullable | verification flow arrives with the mail provider |
| last_login_at | timestamp nullable | |
| created_at / updated_at | timestamps | |

There is **no permanent customer role**. Every user can act as a customer,
and business roles come only from `business_members`.

### `businesses` (the tenant)

| Column | Type | Notes |
|---|---|---|
| id | bigint PK | internal |
| public_id | char(26) unique | ULID |
| name | varchar(150) | |
| slug | varchar(160) unique | generated; falls back to `business-xxxxxx` for non-Latin names |
| category_id | FK → business_categories.id, nullable, **restrict on delete** | Phase 03; required by the API for new businesses |
| description | text nullable | |
| email, phone, website | nullable | Phase 03 contact details |
| timezone | varchar(64) default `UTC` | Phase 03 |
| logo_path, cover_path | nullable | Phase 03; server-side uploads only (none yet) |
| status | varchar(20) default `active`, indexed | `active`, `inactive`, `suspended`, `archived` |
| created_by | FK → users.id, nullable, **null on delete** | audit only; ownership lives in `business_members` |
| idempotency_key | varchar(64) nullable | Phase 03; `UNIQUE (created_by, idempotency_key)` |
| created_at / updated_at, deleted_at | timestamps, soft delete | deleting archives |

### `business_members`

| Column | Type | Notes |
|---|---|---|
| id | bigint PK | |
| public_id | char(26) unique | Phase 03; ULID used by `/members/{member}` (existing rows backfilled) |
| business_id | FK → businesses.id, **cascade on delete** | |
| user_id | FK → users.id, **cascade on delete** | |
| role | varchar(20) | `owner`, `manager`, `employee` |
| status | varchar(20) default `active` | `invited`, `active`, `suspended`. Only `active` grants access. |
| branch_access | varchar(20) default `all` | Phase 03; `all` or `selected` (see `business_member_branch`) |
| joined_at | timestamp nullable | set when the membership becomes active |
| created_at / updated_at | timestamps | |

Constraints and indexes:

- `UNIQUE (business_id, user_id)` prevents duplicate memberships, enforced
  by the database even under concurrent requests.
- `INDEX (user_id, status)` serves "which businesses can this user access?".

A user may belong to many businesses, for example owning one and working in
another.

### Phase 03 tables

| Table | Key columns | Constraints |
|---|---|---|
| `business_categories` | `slug` (public id), `name` JSON `{en, ar}`, `icon`, `sort_order`, `is_active`, `parent_id` | `UNIQUE slug`; self FK restrict |
| `branches` | `public_id`, `business_id`, address fields, `country` char(2), `latitude`/`longitude` decimal(10,7) WGS 84, `timezone`, `status`, `deleted_at` | FK business cascade; `INDEX (business_id, status)` |
| `services` | `public_id`, `branch_id`, `name`, `description`, `estimated_duration_minutes`, `display_order`, `status`, `deleted_at` | FK branch cascade; `INDEX (branch_id, status, display_order)` |
| `business_member_branch` | `business_member_id`, `branch_id` | both FK cascade; `UNIQUE` pair |
| `business_invitations` | `public_id`, `business_id`, `email`, `role`, `branch_access`, `branch_ids` JSON, `token_hash` char(64), `status`, `pending_key` char(64), `invited_by`, `accepted_by`, `expires_at`, `responded_at` | `UNIQUE token_hash`; `UNIQUE pending_key` (one pending invitation per email per business); `INDEX (business_id, status)` |
| `business_audit_logs` | `business_id`, `actor_id`, `action`, `subject_type`/`subject_id`, `properties` JSON, `created_at` | FK business cascade, actor null on delete |

Migrations: `2026_11_05_200100` … `200700`. They only add to the Phase 02
tables, and were verified on SQLite and MariaDB 10.4 (full suite on both).

### Framework tables

| Table | Purpose |
|---|---|
| `personal_access_tokens` | Sanctum tokens (SHA-256 hash only, `expires_at`, `last_used_at`) |
| `password_reset_tokens` | Hashed reset tokens, 60-minute expiry |
| `cache`, `cache_locks` | Database cache store (local fallback for Redis) |
| `jobs`, `job_batches`, `failed_jobs` | Database queue (local fallback for Redis) |
| `migrations` | Migration history |

There is no `sessions` table: the API is stateless.

## Enumerations

Statuses and roles are `VARCHAR` columns cast to PHP backed enums
(`app/Enums`). That keeps migrations portable across MySQL, MariaDB, and
SQLite, and adding a value requires no `ALTER TABLE`.

## Factories and seeders

- Factories: `UserFactory` (states: `unverified`, `suspended`,
  `platformAdmin`), `BusinessFactory` (`suspended`, `inactive`,
  `categorized`), `BusinessMemberFactory` (`owner`, `manager`, `suspended`,
  `invited`, `selectedBranches`), and Phase 03 `BusinessCategoryFactory`,
  `BranchFactory`, `ServiceFactory`, `BusinessInvitationFactory`
  (`withToken`, `expired`, `accepted`). The
  factory password is random per test run; there is no hardcoded password.
- `BusinessCategorySeeder` seeds the 11 launch categories. It is reference
  data: idempotent and run by `DatabaseSeeder` in every environment.
- `DatabaseSeeder` seeds no demo data in production. Locally it runs
  `DemoDataSeeder`, which creates `owner@dory.test`, `employee@dory.test`,
  `customer@dory.test`, and a demo clinic, **only if `SEED_DEMO_PASSWORD` is
  set**.

## Compatibility verification

| Engine | How it was verified | Result |
|---|---|---|
| SQLite (in-memory) | `composer test` | 104 tests passed |
| MariaDB 10.4.32 | `php artisan migrate` on `dory_db`, plus `composer test:mariadb` on `dory_db_testing` | migrations OK, 104 tests passed |
| MySQL 8.x | not available on this machine | run `composer test:mysql` against a MySQL test database before production |

Notes:

- MariaDB 10.4 is supported by Laravel 13 (MariaDB 10.3+), but it is past
  upstream end of life. Use MySQL 8.4 LTS (or a supported MariaDB) for
  staging and production.
- Email uniqueness is case-insensitive on MySQL/MariaDB through the
  collation. On every engine, the API also lower-cases emails before
  validation and storage.

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
| description | text nullable | |
| status | varchar(20) default `active`, indexed | `active`, `suspended`, `archived` |
| created_by | FK → users.id, nullable, **null on delete** | audit only; ownership lives in `business_members` |
| created_at / updated_at | timestamps | |

### `business_members`

| Column | Type | Notes |
|---|---|---|
| id | bigint PK | |
| business_id | FK → businesses.id, **cascade on delete** | |
| user_id | FK → users.id, **cascade on delete** | |
| role | varchar(20) | `owner`, `manager`, `employee` |
| status | varchar(20) default `active` | `invited`, `active`, `suspended`. Only `active` grants access. |
| joined_at | timestamp nullable | set when the membership becomes active |
| created_at / updated_at | timestamps | |

Constraints and indexes:

- `UNIQUE (business_id, user_id)` prevents duplicate memberships, enforced
  by the database even under concurrent requests.
- `INDEX (user_id, status)` serves "which businesses can this user access?".

A user may belong to many businesses, for example owning one and working in
another.

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
  `platformAdmin`), `BusinessFactory` (`suspended`), and
  `BusinessMemberFactory` (`owner`, `manager`, `suspended`, `invited`). The
  factory password is random per test run; there is no hardcoded password.
- `DatabaseSeeder` seeds nothing in production. Locally it runs
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

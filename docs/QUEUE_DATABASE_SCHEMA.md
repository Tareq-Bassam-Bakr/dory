# Queue database schema

Migrations: `backend/database/migrations/2026_11_06_1001…1006_*`. All tables
use InnoDB on MySQL/MariaDB. Public identifiers are ULIDs (`public_id`);
internal auto-increment ids never leave the server.

A recurring pattern: a **nullable "key" column with a unique index** that is
filled only while a row is "live", and set to NULL otherwise (NULLs never
collide). The models fill these keys in their `saving` hooks, so the database
itself refuses a second live row.

## `queues`

| Column | Type | Notes |
|---|---|---|
| id | bigint PK | internal |
| public_id | ulid, unique | API id |
| branch_id | FK branches, cascade | never fillable |
| service_id | FK services, cascade | never fillable |
| name | varchar(150) | |
| ticket_prefix | varchar(6) | `^[A-Z][A-Z0-9]{0,5}$` |
| status | varchar | `active` / `inactive` |
| capacity | uint, null | max **waiting** tickets; null = no limit |
| allow_guest_join, require_guest_name, require_guest_phone | bool | defaults true, true, false |
| estimated_service_duration | usmallint, null | minutes; null = service duration |
| grace_period_seconds | uint | default 120; no-show allowed after it |
| settings | json, null | reserved |
| public_token_hash | char(64), null, unique | SHA-256 of the public link token |
| public_token_encrypted | text, null | the token encrypted with APP_KEY (to show the link again) |
| public_token_rotated_at | timestamp | |
| active_prefix | varchar(40), null, **unique** | `"{branch_id}:{PREFIX}"` while not deleted |
| active_slot | varchar(40), null, **unique** | `"{service_id}:{lane}"` while not deleted (one queue per service) |
| lane | utinyint | always 1 (reserved for multiple lanes) |
| timestamps, deleted_at | | soft delete keeps history |

Index: `(branch_id, status)`.

## `queue_sessions`

| Column | Notes |
|---|---|
| public_id | ulid, unique |
| queue_id | FK queues, cascade |
| business_date | date in the **branch's time zone** |
| session_number | 1, 2, … within the business date |
| status | `open` / `paused` / `closed` |
| opened_at, paused_at, closed_at | |
| opened_by, closed_by | FK users, null on delete |
| close_reason | `closed_by_staff` / `day_ended` |
| active_key | varchar(40), null, **unique**: `queue_id` while open or paused |

Unique `(queue_id, business_date, session_number)`; index `(queue_id, status)`.

## `queue_daily_counters`

| Column | Notes |
|---|---|
| queue_id | FK queues, cascade |
| business_date | date |
| last_number | uint, last number handed out |

Unique `(queue_id, business_date)`. See [TICKET_NUMBERING.md](TICKET_NUMBERING.md).

## `queue_tickets`

| Column | Notes |
|---|---|
| public_id | char(26), unique, **random** (not time-ordered), lower-case |
| queue_session_id, queue_id | FKs, cascade |
| user_id | FK users, null on delete; null for guests |
| ticket_number | `PREFIX-001`, display only |
| sequence_number | the daily number |
| business_date | copy of the session's business date |
| status | see [TICKET_STATE_MACHINE.md](TICKET_STATE_MACHINE.md) |
| priority | smallint, 0 (reserved) |
| guest_name | varchar(80) |
| guest_phone | text, **encrypted** (Laravel `encrypted` cast) |
| public_access_token_hash | char(64): SHA-256 of the guest token; plaintext never stored |
| join_source | `app` / `public_link` |
| idempotency_key_hash | char(64), SHA-256 of scope + Idempotency-Key |
| joined_at, called_at, started_at, completed_at, cancelled_at, ended_at | |
| cancel_reason | `customer_cancelled`, `guest_cancelled`, `queue_closed`, … |
| estimated_wait_seconds, estimated_at | estimate at join time |
| served_by | FK users, null on delete |
| recall_count | |
| active_user_key | varchar(40), null, **unique**: `"{session}:{user}"` while waiting/called/serving |
| active_staff_key | varchar(40), null, **unique**: `served_by` while called/serving |

Unique `(queue_id, business_date, sequence_number)` and
`(queue_id, idempotency_key_hash)`. Indexes: `(queue_session_id, status,
priority, id)` (FIFO lookups), `(user_id, status)`, `(served_by, status)`,
`(public_access_token_hash)`, `(queue_id, status, completed_at)` (estimates).

## `queue_events` (append-only)

`queue_id`, `queue_session_id` (null), `ticket_id` (null), `actor_id` (null:
guest or system, recorded in metadata), `event_type`, `previous_status`,
`new_status`, `metadata` (json, never names, phones or tokens), `created_at`.
The model throws on update and delete. Indexes on `(ticket_id, created_at)`,
`(queue_session_id, created_at)`, `(queue_id, created_at)`.

## `queue_staff_assignments`

`public_id`, `queue_id`, `business_member_id`, `assigned_by`, `assigned_at`,
`unassigned_by`, `unassigned_at`, `active_key` (unique,
`"{queue}:{member}"` while assigned). Removing an assignment keeps the row.

## Verified on

SQLite (tests), MariaDB 10.4 (local: migrate, rollback, migrate again, full
suite). MySQL 8.4: see [MYSQL_84_COMPATIBILITY.md](MYSQL_84_COMPATIBILITY.md).

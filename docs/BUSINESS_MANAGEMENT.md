# Business Management

A business is DORY's tenant: branches, services, the team and invitations
all belong to exactly one business. Endpoints:
[API_ENDPOINTS_PHASE_03.md](API_ENDPOINTS_PHASE_03.md#businesses).

## Data model

`businesses` (Phase 02 table, extended in
`2026_11_05_200200_add_profile_fields_to_businesses_table`):

| Column | Notes |
|---|---|
| `id` | Internal key, never exposed |
| `public_id` | ULID, the API `id` |
| `category_id` | FK `business_categories` (restrict on delete). Nullable for old rows; required by the API |
| `name`, `slug`, `description` | Slug generated from the name, unique including deleted rows; non-Latin names fall back to `business-…` |
| `email`, `phone`, `website` | Optional contact details (phone in E.164) |
| `timezone` | IANA name, default `UTC`; default for new branches |
| `logo_path`, `cover_path` | Reserved for server-side uploads (no upload endpoint yet); exposed as `logo_url` / `cover_url`, never settable by clients |
| `status` | `active`, `inactive`, `suspended`, `archived` |
| `created_by` | Who created the row. **Ownership lives in `business_members`**, not here |
| `idempotency_key` | Client key for safe retries, unique per creator |
| `deleted_at` | Soft delete (archive) |

## Categories

`business_categories`: `slug` (stable public id), `name` JSON
(`{"en": …, "ar": …}`), `icon` (key the clients map to an icon),
`sort_order`, `is_active`, `parent_id` (for future subcategories).

- Seeded by `BusinessCategorySeeder` (reference data: idempotent, safe in
  production). The 11 launch categories are listed in the seeder.
- Retire a category by setting `is_active = false`; never rename a slug or
  delete a category in use (the foreign key restricts deletion).
- The Flutter app has **no hardcoded categories**: it renders what the API
  returns and maps unknown icon keys to a generic icon.
- No logic may branch on a category: every sector uses the same branches,
  services and, later, queues.

```bash
php artisan db:seed --class=BusinessCategorySeeder --force
```

## Lifecycle

| Status | Set by | Effect |
|---|---|---|
| `active` | owner | Normal |
| `inactive` | owner | Hidden from customers (later phases); the team keeps managing it |
| `suspended` | platform (no API yet) | Read-only for every member: writes return `403 business_suspended` |
| `archived` | owner, via DELETE | Soft-deleted; nobody can reach it; pending invitations revoked |

## Creation and ownership

`BusinessService::create()` runs in one transaction:

1. Business row (slug, category, creator, idempotency key).
2. Owner membership (`BusinessMembershipService::persistWithOwner`).
3. Optional first branch, then its services.
4. Audit entry `business.created`.

Any failure rolls everything back, so a business never exists without an
owner and the API never reports success for a partial write (tested by
`BusinessCreationTest::test_a_failure_part_way_through_creates_nothing`).

A user may own and belong to any number of businesses. Ownership cannot be
transferred or granted through the API: invitations and member updates
accept only `manager` and `employee`, and the owner membership cannot be
changed or removed.

## Profile completion

Computed on read (`BusinessProfileCompletion`), never stored: five steps
(category, description, contact, an active branch, an active service in an
active branch), 20% each. Returned as `profile: { percentage, is_complete,
missing }` with `stats: { branches, services, members }`.

## Audit trail

`business_audit_logs` records `business.created`, `business.updated`
(changed field names only), `business.status_changed` (from/to),
`business.deleted`, and the membership events in
[MEMBERSHIP_AND_INVITATIONS.md](MEMBERSHIP_AND_INVITATIONS.md). Entries hold
no tokens, passwords or full payloads. There is no API to read them yet.

## Flutter

| Screen | Route | File |
|---|---|---|
| My businesses | `/business/dashboard` | `features/business_dashboard/presentation/pages/business_dashboard_page.dart` |
| Create business | `/business/create` | `features/businesses/presentation/pages/create_business_page.dart` |
| Onboarding | `/business/create/onboarding` | see [BUSINESS_ONBOARDING.md](BUSINESS_ONBOARDING.md) |
| Business overview | `/business/dashboard/:businessId` | `business_overview_page.dart` |
| Edit profile | `…/:businessId/edit` | `edit_business_page.dart` |

The overview shows status, statistics, profile completion, the actions the
server's `permissions` allow (edit, branches, team, activate/deactivate,
delete) and the queue summary placeholder (queues arrive in a later phase).

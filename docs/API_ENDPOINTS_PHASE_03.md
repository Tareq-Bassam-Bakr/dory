# API Endpoints — Phase 03

All paths are under `/api/v1`. Every response uses the envelope from
[API_CONVENTIONS.md](API_CONVENTIONS.md): `{ success, message, data, meta }`
on success, `{ success: false, message, code, errors }` on failure. Clients
branch on `code`, never on `message`.

Authenticated endpoints need `Authorization: Bearer <token>` (from
`/auth/login` or `/auth/register`, see [API_AUTHENTICATION.md](API_AUTHENTICATION.md))
and reject suspended accounts with `403 account_inactive`.

**Identifiers.** Every `id` is a public ULID (26 lowercase characters).
Categories are identified by their `slug`. Internal numeric ids never
appear in requests or responses.

**Tenant isolation.** `{business}`, `{branch}`, `{service}`, `{member}` and
`{invitation}` are resolved through the caller's own memberships and branch
access. A resource the caller may not reach returns **exactly** the same
404 as one that does not exist (`business_not_found`, `branch_not_found`,
`service_not_found`, `member_not_found`, `invitation_not_found`).

## Summary

| Method | Path | Who | Notes |
|---|---|---|---|
| GET | `/business-categories` | public | Active categories, display order |
| POST | `/businesses` | any signed-in user | Creator becomes owner; optional first branch + services; `Idempotency-Key` |
| GET | `/businesses/manage` | signed-in | Businesses where the caller is an active member (paginated) |
| GET | `/businesses/{business}` | member | Profile, stats, completion, permissions |
| PUT | `/businesses/{business}` | owner / all-branch manager; `status`: owner | Partial update |
| DELETE | `/businesses/{business}` | owner | Archives (soft delete), revokes pending invitations |
| GET | `/businesses/{business}/branches` | member | Only branches the caller may reach (paginated, `?status=`) |
| POST | `/businesses/{business}/branches` | owner / all-branch manager | |
| GET | `/branches/{branch}` | member with access | |
| PUT | `/branches/{branch}` | owner / manager with access | Partial update |
| DELETE | `/branches/{branch}` | owner / all-branch manager | Soft delete; archives its services |
| GET | `/branches/{branch}/services` | member with access | Display order (paginated, `?status=`) |
| POST | `/branches/{branch}/services` | owner / manager with access | |
| GET | `/services/{service}` | member with access | |
| PUT | `/services/{service}` | owner / manager with access | Partial update |
| DELETE | `/services/{service}` | owner / manager with access | Archives (soft delete) |
| GET | `/businesses/{business}/members` | owner / manager | Owner first (paginated) |
| PUT | `/businesses/{business}/members/{member}` | owner | Role and branch access; *addition* |
| DELETE | `/businesses/{business}/members/{member}` | owner | Never the owner or self |
| GET | `/businesses/{business}/invitations` | owner / manager | Open invitations; `?status=all` for history; *addition* |
| POST | `/businesses/{business}/invitations` | owner | Throttled per user |
| DELETE | `/businesses/{business}/invitations/{invitation}` | owner | Revoke; *addition* |
| GET | `/invitations/{token}` | public | Preview, masked email; throttled per IP |
| POST | `/invitations/{token}/accept` | invited account | Throttled per IP |
| POST | `/invitations/{token}/decline` | invited account | Throttled per IP |

*Addition* marks endpoints beyond the Phase 03 list, needed for a complete
team screen (changing roles, listing and revoking invitations).

Permissions are defined once in `app/Services/Business/BusinessAbilities.php`;
see [MULTI_TENANCY.md](MULTI_TENANCY.md#permission-matrix-phase-03).

## Categories

```http
GET /api/v1/business-categories
```

```json
{
  "success": true,
  "message": "Operation completed successfully",
  "data": [
    {
      "slug": "restaurants-cafes",
      "name": { "en": "Restaurants & Cafes", "ar": "المطاعم والمقاهي" },
      "icon": "restaurant",
      "is_active": true,
      "children": []
    }
  ],
  "meta": null
}
```

Not paginated (small, bounded list). Disabled categories and their
subcategories are omitted.

## Businesses

### Create (with onboarding data)

```http
POST /api/v1/businesses
Idempotency-Key: 3f9c2a7be41d4c0e9b7d5a1f6e2c8b40
Content-Type: application/json
```

```json
{
  "name": "Shifa Clinic",
  "category": "healthcare-clinics",
  "description": "Family medicine and pediatrics",
  "email": "contact@shifa.example",
  "phone": "+963 11 123 4567",
  "website": "https://shifa.example",
  "timezone": "Asia/Damascus",
  "branch": {
    "name": "Main branch",
    "address": "Mazzeh Highway 12",
    "city": "Damascus",
    "country": "SY",
    "latitude": 33.5138,
    "longitude": 36.2765,
    "services": [
      { "name": "General consultation", "estimated_duration_minutes": 20 },
      { "name": "Vaccination" }
    ]
  }
}
```

- Required: `name` (2–150), `category` (slug of an active category).
  Everything else is optional; `branch` and `services` may be omitted.
- Emails are lower-cased, phone numbers normalized to E.164, country codes
  upper-cased. Branch time zone defaults to the business time zone; service
  duration defaults to 15 minutes.
- The business, the owner membership, the branch and the services are
  saved in **one transaction**: any failure saves nothing (the client gets
  `500 server_error`, never a success).
- `Idempotency-Key` (optional, 16–64 of `A-Z a-z 0-9 _ -`): a retry with the
  same key returns the business created the first time with `200` and
  `meta.idempotent_replay: true`. A key of a deleted business returns
  `409 idempotency_key_reused`.
- Prohibited (422 if sent): `id`, `public_id`, `slug`, `status`,
  `created_by`, `owner`, `owner_id`, `business_id`, `category_id`,
  `logo_path`, `cover_path`, top-level `services`; unknown keys inside
  `branch` or a service.

Response `201`:

```json
{
  "success": true,
  "message": "Business created successfully",
  "data": {
    "id": "01jc5x2m7v8q9w0e1r2t3y4u5i",
    "name": "Shifa Clinic",
    "slug": "shifa-clinic",
    "description": "Family medicine and pediatrics",
    "category": { "slug": "healthcare-clinics", "name": { "en": "Healthcare & Clinics", "ar": "الرعاية الصحية والعيادات" }, "icon": "healthcare", "is_active": true, "children": [] },
    "email": "contact@shifa.example",
    "phone": "+963111234567",
    "website": "https://shifa.example",
    "timezone": "Asia/Damascus",
    "status": "active",
    "logo_url": null,
    "cover_url": null,
    "stats": { "branches": 1, "services": 2, "members": 1 },
    "profile": { "percentage": 100, "is_complete": true, "missing": [] },
    "membership": { "id": "01jc5x2m7w...", "role": "owner", "branch_access": "all" },
    "permissions": {
      "update": true, "change_status": true, "delete": true,
      "create_branches": true, "view_members": true, "manage_members": true
    },
    "created_at": "2026-11-05T10:00:00+00:00",
    "updated_at": "2026-11-05T10:00:00+00:00"
  },
  "meta": null
}
```

`profile.missing` lists the steps still open: `basics` (category),
`description`, `contact` (email or phone), `branch` (an active branch),
`service` (an active service in an active branch). `permissions` are UI
hints for the caller; the server checks every action again.

### Update

```http
PUT /api/v1/businesses/{business}
```

```json
{ "description": "Open daily", "status": "inactive" }
```

Partial. Profile fields need owner or manager-with-all-branches; `status`
(`active` | `inactive`) needs the owner. `suspended` and `archived` are
platform states and are rejected (422). An empty body is `422` with an error
on `business`. On a suspended business every write is
`403 business_suspended`.

### Delete

```http
DELETE /api/v1/businesses/{business}
```

Owner only. Sets status `archived`, revokes pending invitations and
soft-deletes the row. Members lose access immediately; data is kept.

## Branches

```http
POST /api/v1/businesses/{business}/branches
```

```json
{
  "name": "Abu Rummaneh",
  "address": "Al-Jalaa Street 5",
  "country": "SY",
  "city": "Damascus",
  "region": "Damascus Governorate",
  "latitude": 33.5167,
  "longitude": 36.2833,
  "phone": "+963 11 333 4444",
  "email": "abu.rummaneh@shifa.example",
  "timezone": "Asia/Damascus",
  "status": "active"
}
```

- `name` required (2–150); everything else optional.
- `latitude` −90…90 and `longitude` −180…180 (WGS 84 decimal degrees, the
  OpenStreetMap system), both or neither. On update, send both together
  (`null` both to clear).
- `country`: ISO 3166-1 alpha-2. `status`: `active` | `inactive`.
- `business_id`, `id`, `public_id` are prohibited: a branch can never be
  attached or moved to another business through input.

Branch resource:

```json
{
  "id": "01jc6a...", "business_id": "01jc5x...", "name": "Abu Rummaneh",
  "address": "Al-Jalaa Street 5", "country": "SY", "city": "Damascus", "region": null,
  "latitude": 33.5167, "longitude": 36.2833, "phone": "+963113334444",
  "email": "abu.rummaneh@shifa.example", "timezone": "Asia/Damascus", "status": "active",
  "services_count": 0,
  "permissions": { "update": true, "delete": true, "manage_services": true },
  "created_at": "...", "updated_at": "..."
}
```

## Services

```http
POST /api/v1/branches/{branch}/services
```

```json
{
  "name": "General consultation",
  "description": "Walk-in visit",
  "estimated_duration_minutes": 20,
  "display_order": 1,
  "status": "active"
}
```

- `name` required (2–150), unique within the branch ignoring case
  (archived services excluded); the same name is fine in other branches.
- `estimated_duration_minutes` 1–1440 (default 15); `display_order`
  0–65535 (default: after the last service).
- `branch_id` is prohibited: services never move between branches.
- `DELETE /services/{service}` archives (soft delete); the row stays for
  future ticket history.

## Team members

```http
GET /api/v1/businesses/{business}/members
```

```json
{
  "id": "01jc7m...",
  "user": { "id": "01jc4u...", "name": "Sara K.", "email": "sara@example.com" },
  "role": "manager",
  "status": "active",
  "branch_access": "selected",
  "branches": [ { "id": "01jc6a...", "name": "Abu Rummaneh" } ],
  "joined_at": "2026-11-05T10:00:00+00:00",
  "is_you": false,
  "permissions": { "update": true, "remove": true }
}
```

```http
PUT /api/v1/businesses/{business}/members/{member}
```

```json
{ "role": "manager", "branch_access": "selected", "branches": ["01jc6a..."] }
```

Owner only. `role` is `manager` or `employee` (`owner` → 422: ownership is
not transferable through the API). The owner membership and the caller's
own membership return `403 membership_protected`. `user_id`,
`business_id`, `status`, `public_id` are prohibited.

## Invitations

### Create

```http
POST /api/v1/businesses/{business}/invitations
```

```json
{ "email": "sara@example.com", "role": "manager", "branch_access": "selected", "branches": ["01jc6a..."] }
```

Response `201`:

```json
{
  "invitation": {
    "id": "01jc8i...", "email": "sara@example.com", "role": "manager",
    "branch_access": "selected", "branches": [ { "id": "01jc6a...", "name": "Abu Rummaneh" } ],
    "status": "pending", "expires_at": "2026-11-12T10:00:00+00:00",
    "invited_by": { "name": "Dr. Lina" }, "created_at": "..."
  },
  "delivery": { "channel": "email", "status": "not_configured" },
  "share_url": "dory://app/invitations/<64-character token>"
}
```

- `delivery.status`: `sent` (a real mail transport accepted it),
  `not_configured` (no real transport, or `INVITATION_SEND_EMAILS=false`:
  nothing was sent), `failed` (the transport rejected it).
- `share_url` is returned only when `INVITATION_SHARE_LINKS` is on (default
  outside production). It is the only time the token is available.
- Errors: `409 invitation_pending` (one open invitation per email per
  business), `409 membership_exists`, `422` for self-invites, `owner` role,
  or branches not belonging to the business.

### Respond

```http
GET  /api/v1/invitations/{token}
POST /api/v1/invitations/{token}/accept
POST /api/v1/invitations/{token}/decline
```

Preview (`GET`, works signed out):

```json
{
  "business": { "id": "01jc5x...", "name": "Shifa Clinic", "category": { "slug": "healthcare-clinics", "...": "..." } },
  "role": "manager",
  "email": "sa****@example.com",
  "status": "pending",
  "expires_at": "2026-11-12T10:00:00+00:00",
  "invited_by": { "name": "Dr. Lina" },
  "can_respond": null
}
```

`can_respond` is `null` signed out, otherwise whether the caller may accept.
`accept` returns the business (as above) with the new membership.

| Code | Status | Meaning |
|---|---|---|
| `invitation_not_found` | 404 | Unknown token, or the business was deleted |
| `invitation_expired` | 410 | Past `expires_at` (default 7 days) |
| `invitation_not_pending` | 409 | Already accepted, declined or revoked (single use) |
| `invitation_email_mismatch` | 403 | Signed in with another email address |
| `membership_exists` | 409 | Already a member |

## Error codes added in Phase 03

| Code | Status |
|---|---|
| `business_suspended` | 403 — writes to a platform-suspended business |
| `branch_not_found`, `service_not_found`, `member_not_found`, `invitation_not_found` | 404 |
| `membership_protected` | 403 |
| `invitation_pending`, `invitation_not_pending`, `membership_exists`, `idempotency_key_reused` | 409 |
| `invitation_expired` | 410 |
| `invitation_email_mismatch` | 403 |

## Rate limits

| Limiter | Default | Applies to |
|---|---|---|
| `api` | 60/min per user or IP | every route |
| `invitations` | 10/min per user | `POST .../invitations` |
| `invitation-token` | 30/min per IP | `GET/POST /invitations/{token}…` |

Configured with `RATE_LIMIT_INVITATIONS_PER_MINUTE` and
`RATE_LIMIT_INVITATION_TOKEN_PER_MINUTE`.

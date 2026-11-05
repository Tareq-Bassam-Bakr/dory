# Service Management

A service is something a branch offers: a consultation, a counter
operation, a haircut. Endpoints:
[API_ENDPOINTS_PHASE_03.md](API_ENDPOINTS_PHASE_03.md#services).

## One generic model

There is a single `services` table and a single implementation for every
sector. There are no clinic, restaurant or bank service types, and no code
branches on the business category. Sector-specific needs will be expressed
as data (for example optional fields), not as separate implementations.

`services`: `id`, `public_id` (ULID), `branch_id` (FK, cascade), `name`,
`description`, `estimated_duration_minutes` (1–1440, default 15),
`display_order` (default: after the last service), `status`
(`active` | `inactive`), timestamps, `deleted_at`. Index
`(branch_id, status, display_order)`.

## Rules

- Services always belong to one branch. `branch_id` is prohibited in
  input; a service cannot be moved to another branch or tenant.
- Names are unique within a branch, ignoring case; archived services do
  not count. The same name may exist in other branches. (Checked in
  validation. There is no database constraint because soft-deleted rows
  must not block reuse; under a rare race two identical names could both
  be saved.)
- DELETE **archives** (soft delete): the service disappears from lists but
  its row stays for future ticket history.
- Access follows the parent branch: whoever may manage a branch manages its
  services; services of branches the user cannot reach are `404
  service_not_found`, identical to missing ones.

## Flutter

| Screen | Route |
|---|---|
| Services list | `/business/dashboard/:businessId/branches/:branchId/services` |
| Create service | `…/services/new` |
| Edit service | `…/services/:serviceId/edit` (includes activate/deactivate and archive) |

Files under `lib/features/services/`.

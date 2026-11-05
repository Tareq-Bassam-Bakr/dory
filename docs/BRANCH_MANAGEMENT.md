# Branch Management

A branch is a physical location of a business. Endpoints:
[API_ENDPOINTS_PHASE_03.md](API_ENDPOINTS_PHASE_03.md#branches).

## Data model

`branches`: `id`, `public_id` (ULID), `business_id` (FK, cascade), `name`,
`address`, `country` (ISO 3166-1 alpha-2), `city`, `region`, `latitude`,
`longitude` (`decimal(10,7)`), `phone`, `email`, `timezone`, `status`
(`active` | `inactive`), timestamps, `deleted_at`. Index
`(business_id, status)`.

- Every branch belongs to exactly one business. `business_id` is not
  fillable and is prohibited in requests, so input can never attach a
  branch to, or move it into, another tenant.
- Deleting a branch soft-deletes it and archives its services in the same
  transaction; rows stay for future queue history.

## Location data (OpenStreetMap)

Coordinates are WGS 84 decimal degrees, the system used by OpenStreetMap,
Nominatim and every OSM-based map. Latitude −90…90, longitude −180…180,
both or neither, seven decimals (about 1 cm). Google Maps is not used.
Phase 03 enters coordinates as numbers; map display and address search
(Nominatim) come with the Maps phase. Note for that phase: the public OSM
tile and Nominatim servers have usage policies that forbid heavy
commercial use; production needs its own or a commercial OSM provider.

## Who may do what

| Action | Owner | Manager (all branches) | Manager (selected) | Employee |
|---|:-:|:-:|:-:|:-:|
| List / view | all | all | assigned only | own access |
| Create | ✓ | ✓ | ✗ | ✗ |
| Update, (de)activate | ✓ | ✓ | assigned only | ✗ |
| Delete | ✓ | ✓ | ✗ | ✗ |

"Selected" access with no branches left (for example after the only
assigned branch was deleted) reaches **nothing**; it never widens to all
branches. A branch the user may not reach returns the same
`404 branch_not_found` as a missing branch.

## Flutter

| Screen | Route |
|---|---|
| Branch list | `/business/dashboard/:businessId/branches` |
| Create branch | `…/branches/new` |
| Branch details | `…/branches/:branchId` |
| Edit branch | `…/branches/:branchId/edit` |

Files under `lib/features/branch_management/`. The list shows status and
service counts, with empty, error (retry) and permission-aware states; the
add/edit/delete actions follow the server's `permissions`.

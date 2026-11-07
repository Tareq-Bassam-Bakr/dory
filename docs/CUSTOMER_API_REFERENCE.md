# Customer API reference

Base path `/api/v1`. Responses use the standard envelope
([API_CONVENTIONS.md](API_CONVENTIONS.md)). Lists have `meta.pagination`.
Anything not public is the same 404 as something unknown.

## New in Phase 05

| Method and path | Auth | Purpose |
|---|---|---|
| `GET /discovery/businesses` | None | Search; see [parameters](SEARCH_AND_FILTERS.md) |
| `GET /discovery/businesses/{slug or id}` | None | Business profile |
| `GET /discovery/businesses/{slug or id}/branches` | None | Public branches. `latitude`, `longitude` sort by distance |
| `GET /discovery/nearby` | None | Branches around a required location, nearest first. Up to 100 per page |
| `GET /discovery/queues` | None | Queues accepting tickets, lowest wait first |
| `GET /discovery/cities` | None | Cities with public branches. `q` filters |
| `GET /discovery/branches/{id}` | None | Branch page |
| `GET /discovery/branches/{id}/services` | None | Active services, each with its queue |
| `GET /public/queues/{queue id}` | None | Queue before joining; 404 if its business is not public |
| `POST /public/queues/{queue id}/join` | None | Guest join by id |
| `GET /me/favorites` | Bearer | Saved businesses |
| `POST /me/favorites` | Bearer | Save `{"business": "<id>"}` |
| `DELETE /me/favorites/{id}` | Bearer | Remove |
| `POST /tickets/{id}/review` | Bearer | Rate a completed ticket `{"rating": 1..5}` |

## Reused from earlier phases

| Method and path | Notes |
|---|---|
| `GET /business-categories` | The category list, instead of a new `/discovery/categories` |
| `POST /queues/{id}/join` | Signed-in join. Now also refuses inactive businesses, branches and services |
| `GET /me/tickets` | Now accepts `group=active\|history` and `status=waiting,called` |
| `GET /tickets/{id}/position` | Position and estimate |
| `POST /tickets/{id}/cancel` | |
| `GET /public/queues/{token}` | The QR link. New fields below |
| `POST /public/queues/{token}/join` | |
| `GET /public/tickets/{id}`, `POST .../cancel` | `X-Ticket-Token` header |
| `PUT /users/profile` | Name and phone |

## Changes from the Phase 05 plan

The plan offered these as suggestions. Each difference has a reason:

| Suggested | Built | Why |
|---|---|---|
| `GET /businesses/{slug}` | `GET /discovery/businesses/{slug}` | `/businesses/{id}` is a member-only route; a slug can look like an id |
| `GET /branches/{branch}` | `GET /discovery/branches/{id}` | `/branches/{id}` is member-only |
| `GET /branches/{branch}/public-services` | `GET /discovery/branches/{id}/services` | Same |
| `GET /branches/{branch}/public-queues` | Part of the services list | Each service has its queue |
| `GET /discovery/categories` | `GET /business-categories` | It already existed |

Added beyond the plan: `/discovery/queues` and `/discovery/cities` (the home
screen and the manual location picker need them), and guest join by queue id.

## Business card

```json
{
  "id": "01k...", "slug": "shifa-clinic", "name": "Shifa Clinic",
  "category": {"slug": "healthcare-clinics", "name": {"en": "...", "ar": "..."}, "icon": "healthcare", "is_active": true},
  "logo_url": null, "cover_url": null,
  "branches_count": 2, "city": "Damascus", "country": "SY", "distance_km": 1.24,
  "open_queues_count": 1, "accepting_queues_count": 1, "lowest_wait_minutes": 15,
  "rating": {"average": 4.25, "count": 8},
  "is_favorite": false
}
```

The profile adds `description`, `email`, `phone` and `website`. `rating` is null
until enough real ratings exist. `distance_km` is null without a location.

## Branch

`id`, `name`, `address`, `city`, `region`, `country`, `latitude`, `longitude`,
`phone`, `timezone`, `distance_km`, `services_count`, `open_queues_count`,
`accepting_queues_count`, `lowest_wait_minutes`, and `business` (`id`, `slug`,
`name`, `logo_url`, `category`). There are no opening hours.

## Queue availability

```json
{
  "id": "01k...", "name": "Consultations",
  "status": "open", "unavailable_reason": null,
  "accepting_joins": true, "is_full": false,
  "waiting_count": 3, "capacity": 10, "capacity_remaining": 7,
  "estimated_wait": {"seconds": 900, "minutes": 15, "basis": "configured"},
  "allow_guest_join": true, "require_guest_name": true, "require_guest_phone": false
}
```

`status`: `open`, `paused`, `closed`, `inactive`. `unavailable_reason`:
`queue_inactive`, `business_unavailable`, `branch_inactive`,
`service_unavailable`.

`GET /public/queues/...` returns the same fields plus `business` (`id`, `slug`,
`name`), `branch` (`id`, `name`, `city`, `address`), `service` and `now_serving`.

## Ticket additions

`CustomerTicketResource` gained `business.slug`, `business.logo_url`,
`branch.city`, `branch.address`, `can_review` and `my_rating`. Existing fields
did not change.

## Error codes

| Code | Status | Meaning |
|---|---|---|
| `business_not_found`, `branch_not_found` | 404 | Unknown or not public |
| `queue_closed`, `queue_paused`, `queue_full`, `queue_inactive` | 409 | From Phase 04 |
| `business_unavailable` | 409 | The business cannot take customers |
| `branch_inactive` | 409 | The branch cannot take customers |
| `service_unavailable` | 409 | The service is inactive or deleted |
| `guest_join_disabled` | 403 | From Phase 04 |
| `ticket_not_reviewable` | 409 | Not completed, or the caller is on the business's team |
| `already_reviewed` | 409 | |
| `too_many_requests` | 429 | |

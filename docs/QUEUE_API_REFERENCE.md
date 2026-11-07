# Queue API reference (Phase 04)

Base URL `/api/v1`. Responses use the standard envelope
(`docs/API_CONVENTIONS.md`): `{success, message, data, meta}` or
`{success: false, message, code, errors}`. Ids are public ULIDs. Lists are
paginated (`?per_page=`, `meta.pagination`).

Customer discovery, favorites, ratings and guest join by queue id are in
[CUSTOMER_API_REFERENCE.md](CUSTOMER_API_REFERENCE.md). Since Phase 05, joining
also fails with `business_unavailable`, `branch_inactive` or
`service_unavailable`.

Legend: 🔒 bearer token (Sanctum) · 👥 member of the business who reaches the
branch · **M** manage · **O** operate (see [QUEUE_SECURITY.md](QUEUE_SECURITY.md)).

## Queue configuration

| Method | Path | Access | Notes |
|---|---|---|---|
| GET | `/businesses/{business}/queues` | 🔒👥 | **added**: every reachable queue of the business (overview screen) |
| GET | `/branches/{branch}/queues` | 🔒👥 | |
| POST | `/branches/{branch}/queues` | 🔒 M | body below; 201 |
| GET | `/queues/{queue}` | 🔒👥 | |
| PUT | `/queues/{queue}` | 🔒 M | partial update; `service` cannot change |
| DELETE | `/queues/{queue}` | 🔒 M | soft delete; `409 queue_has_active_session` |
| GET | `/queues/{queue}/public-link` | 🔒 M | `{url, qr_payload, rotated_at, allow_guest_join}` |
| POST | `/queues/{queue}/rotate-public-token` | 🔒 M | new link; old one stops working |

Create body:

```json
{
  "service": "01j…",              // a service of this branch, without a queue
  "name": "Haircuts",
  "ticket_prefix": "HC",          // ^[A-Z][A-Z0-9]{0,5}$, upper-cased, unique per branch
  "status": "active",             // optional: active | inactive
  "capacity": 30,                 // optional, 1–10000, null = no limit (waiting tickets)
  "allow_guest_join": true,       // optional
  "require_guest_name": true,     // optional
  "require_guest_phone": false,   // optional
  "estimated_service_duration": 12,// optional minutes, null = service duration
  "grace_period_seconds": 120     // optional 0–3600
}
```

Queue resource: `id, name, business{id,name}, branch{id,name},
service{id,name}, ticket_prefix, status, capacity, allow_guest_join,
require_guest_name, require_guest_phone, estimated_service_duration,
effective_service_minutes, grace_period_seconds, settings, current_session
(session or null), permissions{manage, operate}, created_at, updated_at`.
Never contains the link token.

## Sessions

| Method | Path | Access | Errors |
|---|---|---|---|
| POST | `/queues/{queue}/open` | 🔒 O | `queue_inactive`, `session_already_active` |
| POST | `/queues/{queue}/pause` | 🔒 O | `no_active_session`, `session_not_open` |
| POST | `/queues/{queue}/resume` | 🔒 O | `no_active_session`, `session_not_paused` |
| POST | `/queues/{queue}/close` | 🔒 O | `no_active_session` |
| GET | `/queues/{queue}/current-session` | 🔒👥 | `data: null` when closed |
| GET | `/queues/{queue}/sessions` | 🔒👥 | newest first, with `tickets_count` |

Session resource: `id, business_date, session_number, status, opened_at,
paused_at, closed_at, close_reason, tickets_count?`.

## Live view and serving

| Method | Path | Access | Notes |
|---|---|---|---|
| GET | `/queue-sessions/{session}/dashboard` | 🔒👥 | counts per status, `next_up` (5), `in_service`, `my_ticket`, `estimated_wait`, `permissions`, `is_current_session` |
| GET | `/queue-sessions/{session}/tickets?status=waiting,called` | 🔒👥 | active statuses in queue order, others newest first; `422` for unknown statuses |
| POST | `/queue-sessions/{session}/call-next` | 🔒 O | `queue_closed`, `staff_busy`, `no_waiting_tickets` |
| GET | `/tickets/{ticket}` | 🔒👥 | **added**: details with `events`; full guest phone for operators |
| POST | `/tickets/{ticket}/call` | 🔒 M | out of order |
| POST | `/tickets/{ticket}/start` | 🔒 O | caller or manager |
| POST | `/tickets/{ticket}/complete` | 🔒 O | caller or manager |
| POST | `/tickets/{ticket}/skip` | 🔒 O | waiting only |
| POST | `/tickets/{ticket}/no-show` | 🔒 O | after the grace period |
| POST | `/tickets/{ticket}/recall` | 🔒 O | back to its place |

Staff ticket resource: `id, ticket_number, status, priority, join_source,
customer{type: customer|guest, name, phone (masked unless details+operator)},
position, served_by{id,name,is_you}, recall_count, cancel_reason,
waited_seconds, service_seconds, no_show_allowed_at, joined_at, called_at,
started_at, completed_at, ended_at, events? [{type, from, to, actor, reason,
at}]`.

Ticket action errors: `invalid_ticket_transition`, `ticket_state_changed`,
`ticket_handled_by_other_staff`, `grace_period_active`, `staff_busy`,
`queue_closed`.

## Staff assignments

| Method | Path | Access | Notes |
|---|---|---|---|
| GET | `/queues/{queue}/staff` | 🔒👥 | active assignments |
| POST | `/queues/{queue}/staff` | 🔒 M | `{"member": "<membership id>"}`; 201 new, 200 existing; `422 member` if not an active manager/employee of the branch |
| DELETE | `/queues/{queue}/staff/{assignment}` | 🔒 M | keeps history |

## Customers (signed in)

| Method | Path | Notes |
|---|---|---|
| POST | `/queues/{queue}/join` 🔒 | optional `Idempotency-Key`; 201 new, 200 replay. Errors: `queue_inactive`, `queue_closed`, `queue_paused`, `queue_full`, `duplicate_active_ticket` |
| GET | `/me/tickets` 🔒 | active first, then newest |
| GET | `/tickets/{ticket}/position` 🔒 | own tickets only (others: 404) |
| POST | `/tickets/{ticket}/cancel` 🔒 | waiting only (`cancellation_not_allowed`) |

Customer ticket resource: `id, ticket_number, status, business_date,
queue{id,name,session_status}, business{id,name}, branch{id,name},
service{name}, position, people_ahead, estimated_wait{seconds,minutes,basis},
now_serving[], arrive_by, can_cancel, cancel_reason, joined_at, called_at,
started_at, completed_at, ended_at` (+ `access_token` once, for guests).

## Guests (public link)

| Method | Path | Notes |
|---|---|---|
| GET | `/public/queues/{token}` | name, business, branch, service, `status` (open/paused/closed/inactive), `accepting_joins`, `waiting_count`, `now_serving`, `estimated_wait`, join rules. No customer data. |
| POST | `/public/queues/{token}/join` | `{name?, phone?, consent?}`; returns the ticket and `access_token` **once**; `guest_join_disabled` |
| GET | `/public/tickets/{ticket}` | header `X-Ticket-Token` required (else 404) |
| POST | `/public/tickets/{ticket}/cancel` | header `X-Ticket-Token` required |

Web pages (not under `/api`): `GET/POST /join/{token}`, `GET /ticket/{id}`,
`POST /ticket/{id}/cancel` ([GUEST_JOINING.md](GUEST_JOINING.md)).

## Changes from the Phase 04 plan

- Added `GET /businesses/{business}/queues` (overview across branches) and
  `GET /tickets/{ticket}` (staff ticket details).
- `POST /queues/{queue}/join` is the **signed-in** join; guests use
  `POST /public/queues/{token}/join` or the web page.
- `GET /tickets/{ticket}/position` returns the whole customer ticket
  (position included), so one call refreshes the screen.
- Added web pages behind the QR code, since a QR code must work without the
  app.

## Error codes

| HTTP | Codes |
|---|---|
| 404 | `queue_not_found`, `session_not_found`, `ticket_not_found`, `assignment_not_found` (also for "not yours") |
| 403 | `forbidden`, `business_suspended`, `guest_join_disabled`, `ticket_handled_by_other_staff` |
| 409 | `queue_inactive`, `queue_closed`, `queue_paused`, `queue_full`, `duplicate_active_ticket`, `invalid_ticket_transition`, `ticket_state_changed`, `no_waiting_tickets`, `staff_busy`, `grace_period_active`, `session_already_active`, `no_active_session`, `session_not_open`, `session_not_paused`, `queue_has_active_session`, `cancellation_not_allowed` |
| 422 | `validation_failed` (field errors, e.g. `ticket_prefix`, `service`, `member`, `idempotency_key`) |
| 429 | `too_many_requests` |

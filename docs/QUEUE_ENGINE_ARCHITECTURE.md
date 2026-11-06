# Queue engine architecture (Phase 04)

DORY has **one generic queue engine** for every business category (clinic,
barber, bank, government office…). There is no category-specific code: a
category only changes labels and defaults elsewhere in the app.

## Concepts

| Concept | Meaning | Table |
|---|---|---|
| **Queue** | The permanent configuration of one service in one branch. Each service has exactly one live queue. | `queues` |
| **Session** | One working period of a queue, from *open* to *close*, on one business day. At most one session per queue is open or paused. | `queue_sessions` |
| **Daily counter** | The last ticket number used by a queue on a business day. | `queue_daily_counters` |
| **Ticket** | A customer's (or guest's) place in a session. | `queue_tickets` |
| **Event** | Append-only history of everything that happened to a queue, session or ticket. | `queue_events` |
| **Staff assignment** | An employee allowed to operate a queue. | `queue_staff_assignments` |

## Layers (backend)

```
HTTP (routes/api.php, routes/web.php)
  ├─ Route bindings: TenantResolver (members) · PublicQueueResolver (customers, guests)
  ├─ Policies: QueuePolicy (view / manage / operate), BranchPolicy::manageQueues
  ├─ Form requests: validation + authorization before any work
  └─ Controllers (thin): Api/V1/Queues/*, Web/GuestQueueController
Services (app/Services/Queues)
  ├─ QueueService            configuration, delete, public link token
  ├─ QueueSessionService     open / pause / resume / close, day roll-over
  ├─ TicketJoinService       joining (customers and guests), idempotency
  ├─ TicketNumberAllocator   atomic daily counter
  ├─ QueueOperationsService  call next / call / start / complete / skip / no-show / recall
  ├─ TicketCancellationService  holder cancels a waiting ticket
  ├─ TicketStateMachine      the only code that changes a ticket's status
  ├─ QueueEventRecorder      append-only history + QueueActivity event
  ├─ TicketPositions         position, people ahead, now serving
  ├─ WaitTimeEstimator       contract; HistoricalWaitTimeEstimator default
  ├─ QueueStaffService       assignments
  └─ GuestTicketCookies      guest web page cookies
Models (app/Models): Queue, QueueSession, QueueTicket, QueueEvent, QueueStaffAssignment
Database: invariants enforced by unique indexes (see QUEUE_DATABASE_SCHEMA.md)
```

Rules that hold everywhere:

- **Every state change goes through one service method inside one database
  transaction**, with rows locked in a fixed order (session → counter →
  ticket). See [CONCURRENCY_STRATEGY.md](CONCURRENCY_STRATEGY.md).
- **The database is the source of truth for uniqueness.** Ticket numbers,
  one active session, one active ticket per customer and one customer per
  staff member are all unique indexes, not just application checks.
- **Every ticket transition is validated** by `TicketStatus::allowedTransitions()`
  and recorded as a `queue_events` row in the same transaction
  ([TICKET_STATE_MACHINE.md](TICKET_STATE_MACHINE.md)).
- **Tenancy**: staff routes resolve `{queue}`, `{queueSession}`, `{ticket}`
  only through the user's memberships and branch access; anything else is the
  same 404 as a missing record.

## Real-time readiness (not implemented)

There is **no real-time push in Phase 04**. Every recorded event dispatches
`App\Events\QueueActivity` (`ShouldDispatchAfterCommit`: nothing is announced
for a rolled-back change). No listener exists yet. Laravel Reverb can later
broadcast it without touching the engine. Until then the Flutter screens and
the guest web page **poll** (every 15 s and 20 s) and say so on screen.

## Priority readiness (not implemented)

Phase 04 is FIFO. Tickets already have a `priority` column (always 0) and every
ordering is `priority DESC, id ASC`, with a matching index, so priority queues
can be added without changing call-next, positions or estimates.

## Flutter

`lib/features/queues` follows the app's Clean Architecture: domain entities and
a repository interface, an API repository, Riverpod providers and
`QueueActions` (mutations that refresh what they change), and 12 screens under
the business **Queues** tab. The customer-side ticket experience is a later
phase; customers can already join through the API and the guest web page.

## Related documents

[QUEUE_DATABASE_SCHEMA](QUEUE_DATABASE_SCHEMA.md) ·
[QUEUE_LIFECYCLE](QUEUE_LIFECYCLE.md) ·
[TICKET_STATE_MACHINE](TICKET_STATE_MACHINE.md) ·
[TICKET_NUMBERING](TICKET_NUMBERING.md) ·
[GUEST_JOINING](GUEST_JOINING.md) ·
[QUEUE_SECURITY](QUEUE_SECURITY.md) ·
[CONCURRENCY_STRATEGY](CONCURRENCY_STRATEGY.md) ·
[WAIT_TIME_ESTIMATION](WAIT_TIME_ESTIMATION.md) ·
[QUEUE_API_REFERENCE](QUEUE_API_REFERENCE.md) ·
[MYSQL_84_COMPATIBILITY](MYSQL_84_COMPATIBILITY.md)

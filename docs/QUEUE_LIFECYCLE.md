# Queue lifecycle

## Queue configuration

1. A manager (owner, or manager with access to the branch) creates a queue for
   a **service of that branch**: `POST /branches/{branch}/queues`. One live
   queue per service; the ticket prefix is unique per branch.
2. The queue can be edited (`PUT /queues/{queue}`), deactivated
   (`status: inactive`: cannot be opened or joined) and soft-deleted
   (`DELETE`, refused while a session is open or paused). Deleting frees the
   prefix and the service, keeps all history, and kills the public link.

## Sessions

```
          open                pause
 (none) ───────▶  OPEN  ───────────────▶  PAUSED
                   ▲  │                     │
                   │  │ close        resume │
                   │  ▼                     │
                   │ CLOSED ◀───────────────┘ close
                   └──── open again (next session number, same day)
```

| Action | Who | Effect |
|---|---|---|
| open | operators | New session for **today in the branch's time zone**, `session_number` = last of the day + 1. Refused if a session is already open/paused today (`session_already_active`) or the queue is inactive. |
| pause | operators | No new joins (`queue_paused`). Staff **keep calling and serving** the waiting list. |
| resume | operators | Joins accepted again. |
| close | operators | See the closing policy below. |

Operators are managers of the branch and employees assigned to the queue.

### Closing policy

- **WAITING** tickets become **CANCELLED** with `cancel_reason = queue_closed`.
- **CALLED** and **SERVING** tickets are **kept**: staff can still start,
  complete or mark them no-show, so nobody already at the counter is dropped.
- Every affected ticket gets its own event; the `session_closed` event records
  how many waiting tickets were closed. All in one transaction.

### Day roll-over

A session left open past midnight (branch time) keeps working: tickets are
numbered under the **session's** business date. When someone **opens the queue
on a later day**, the stale session is closed first with
`close_reason = day_ended` and its waiting tickets become **EXPIRED**; the new
session starts numbering at 1 for the new day.

No scheduler is required for correctness. A nightly job that closes stale
sessions can be added later for tidier reports.

## Joining

Customers join an **open** session (`POST /queues/{queue}/join`), guests via
the public link (`POST /public/queues/{token}/join` or the web page). See
[GUEST_JOINING.md](GUEST_JOINING.md) and
[TICKET_NUMBERING.md](TICKET_NUMBERING.md).

Refusals: `queue_inactive`, `queue_closed` (no session), `queue_paused`,
`queue_full` (capacity = waiting tickets), `duplicate_active_ticket`,
`guest_join_disabled`.

## Serving

`call-next` → `start` → `complete`, with `skip`, `no-show` and `recall`; see
[TICKET_STATE_MACHINE.md](TICKET_STATE_MACHINE.md). A staff member serves one
customer at a time.

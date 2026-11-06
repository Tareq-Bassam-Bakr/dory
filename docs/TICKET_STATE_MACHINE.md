# Ticket state machine

`App\Enums\TicketStatus` defines the states and the allowed transitions;
`App\Services\Queues\TicketStateMachine::transition()` is the **only** code that
changes a ticket's status. It requires a transaction and a locked ticket row,
rejects anything not in the table (`409 invalid_ticket_transition`), sets the
matching timestamps and staff fields, saves, and records a `queue_events` row
in the same transaction.

```
                 call / call-next            start                complete
   WAITING ─────────────────────────▶ CALLED ────────▶ SERVING ──────────▶ COMPLETED
    │  ▲                               │  │                │
    │  └──────────── recall ───────────┘  │ no-show        │ (cancel, internal)
    │                                     ▼                ▼
    ├── skip ──────────────────────▶ SKIPPED          NO_SHOW      CANCELLED
    ├── cancel (holder, or queue closed) ─────────────────────────▶ CANCELLED
    └── day ended ────────────────────────────────────────────────▶ EXPIRED
```

| From | Allowed to |
|---|---|
| waiting | called, cancelled, skipped, expired |
| called | serving, no_show, waiting (recall) |
| serving | completed, cancelled |
| completed, cancelled, skipped, no_show, expired | — (terminal) |

Terminal states set `ended_at`.

## Actions

| Endpoint | Transition | Who | Extra rules |
|---|---|---|---|
| `POST /queue-sessions/{s}/call-next` | waiting → called | operators | FIFO (`priority DESC, id ASC`); open **or paused** session; caller must be free (`staff_busy`); `no_waiting_tickets` if empty |
| `POST /tickets/{t}/call` | waiting → called | managers | out of order |
| `POST /tickets/{t}/start` | called → serving | the caller, or a manager | otherwise `ticket_handled_by_other_staff` |
| `POST /tickets/{t}/complete` | serving → completed | the caller, or a manager | records `service_seconds` |
| `POST /tickets/{t}/skip` | waiting → skipped | operators | e.g. the customer left |
| `POST /tickets/{t}/no-show` | called → no_show | the caller, or a manager | only after `called_at + grace_period_seconds` (`grace_period_active`) |
| `POST /tickets/{t}/recall` | called → waiting | the caller, or a manager | back to its **original place** (it is next again); staff member freed; `recall_count + 1` |
| `POST /tickets/{t}/cancel` | waiting → cancelled | the ticket's customer | `cancellation_not_allowed` once called |
| `POST /public/tickets/{t}/cancel` | waiting → cancelled | the guest (token) | same |
| close session | waiting → cancelled | operators | `cancel_reason = queue_closed` |
| open on a later day | waiting → expired | system | `cancel_reason` unset, event reason `day_ended` |

`served_by` is set on call and kept through service; `active_staff_key`
(unique) makes "one customer per staff member" a database guarantee.

## Concurrency

Every action locks **session → ticket**, re-reads the ticket, and fails with
`409 ticket_state_changed` if its status differs from what the request was
based on (someone else acted first). See
[CONCURRENCY_STRATEGY.md](CONCURRENCY_STRATEGY.md).

## Events

`joined`, `called`, `service_started`, `service_completed`, `cancelled`,
`skipped`, `no_show`, `expired`, `recalled`, `session_opened`,
`session_paused`, `session_resumed`, `session_closed`. Events are append-only
(the model throws on update/delete) and never contain names, phone numbers or
tokens.

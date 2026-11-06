# Concurrency strategy

Many customers join and several staff members call at the same moment. The
engine relies on **database transactions, row locks and unique indexes**; it
does not depend on Redis, on application locks or on luck.

## Principles

1. **One transaction per operation**, retried up to 3 times on deadlock /
   lock-wait errors (`DB::transaction($callback, 3)`).
2. **A single serialization point per queue: the active session row.** Joins,
   call-next and ticket actions start with `SELECT … FOR UPDATE` on it. Under
   REPEATABLE READ (InnoDB default) the first statement being the locking read
   means every later read in the transaction sees all previously committed
   work.
3. **Fixed lock order: session → daily counter → ticket.** Every code path
   takes locks in that order, so two operations cannot wait on each other in a
   cycle.
4. **Invariants live in unique indexes**, so even a bug cannot store a bad
   state:

| Invariant | Enforced by |
|---|---|
| one live queue per service | `queues.active_slot` |
| unique prefix per branch | `queues.active_prefix` |
| one open/paused session per queue | `queue_sessions.active_key` |
| unique daily ticket number | `queue_tickets (queue_id, business_date, sequence_number)` |
| one active ticket per customer per session | `queue_tickets.active_user_key` |
| one customer per staff member | `queue_tickets.active_staff_key` |
| one ticket per idempotency key | `queue_tickets (queue_id, idempotency_key_hash)` |
| one active assignment per member and queue | `queue_staff_assignments.active_key` |

   Unique violations are translated into the same API errors as the
   application checks (`session_already_active`, `duplicate_active_ticket`,
   `staff_busy`, `prefix_taken`…).

5. **Numbers come from an atomic upsert**, never `MAX() + 1`
   ([TICKET_NUMBERING.md](TICKET_NUMBERING.md)).
6. **Stale reads are detected**: ticket actions compare the locked row with
   the state the request was based on and answer `409 ticket_state_changed`.
7. **Side effects after commit**: `QueueActivity` implements
   `ShouldDispatchAfterCommit`; nothing is announced for rolled-back work.

## Operation by operation

| Operation | Locks | Guards |
|---|---|---|
| open | queue row, then active session | `active_key` unique; stale session closed in the same transaction |
| join | active session; counter row | capacity and duplicate checks after the lock; `active_user_key`, sequence unique, idempotency unique |
| call next | active session; next waiting ticket (`priority DESC, id ASC`) | staff free check; `active_staff_key` |
| call / start / complete / skip / no-show / recall | ticket's session; ticket | status re-check (`ticket_state_changed`); state machine table |
| close | active session; its waiting tickets | each transition recorded |
| cancel by holder | ticket's session; ticket | waiting only |

Because joins of one queue are serialized on its session row, throughput per
queue is bounded by one short transaction at a time (a few milliseconds);
different queues never block each other.

## Proof: multi-process tests

`tests/Feature/Concurrency/QueueConcurrencyTest.php` starts up to **16
separate PHP processes** (`tests/Concurrency/worker.php`), each with its own
database connection, released at the same instant. Only the database stands
between them, as with concurrent HTTP requests.

| Test | Expected | MariaDB 10.4 (local) |
|---|---|---|
| harness control: 16 workers run an unsafe read-then-write counter | updates are **lost** (proves the workers overlap) | ✅ |
| 16 different customers join at once | numbers exactly 1…16, counter = 16 | ✅ |
| one customer joins 8 times at once | 1 ticket, 7 × `duplicate_active_ticket` | ✅ |
| 8 retries with one Idempotency-Key | 1 ticket, all 8 get it | ✅ |
| capacity 5, 16 guests at once | 5 tickets, 11 × `queue_full` | ✅ |
| 6 staff call next at once, 10 waiting | 6 different tickets, the first six (FIFO) | ✅ |
| one staff member calls 6 times at once | 1 call, 5 × `staff_busy` | ✅ |
| 6 opens at once | 1 session, 5 × `session_already_active` | ✅ |

These tests are **skipped on SQLite**: SQLite serializes all writes to the
whole database file, so passing there would prove nothing about row locking.
SQLite results are never presented as concurrency evidence.

MySQL 8.4: see [MYSQL_84_COMPATIBILITY.md](MYSQL_84_COMPATIBILITY.md).

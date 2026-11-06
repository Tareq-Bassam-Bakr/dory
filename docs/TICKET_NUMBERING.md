# Ticket numbering

## Format

`PREFIX-NNN`: the queue's prefix, a dash, the daily number padded to three
digits: `BAR-001`, `BAR-002` … `BAR-999`, then `BAR-1000` (padding is a
minimum, numbers never wrap).

- The prefix is set per queue (1–6 capital letters or digits, starting with a
  letter), unique among the branch's live queues.
- Numbers **restart at 1 every business day**, where the day is computed in
  the **branch's time zone** (`branches.timezone`; UTC if unset). In Damascus
  (UTC+3), 00:10 local time on 7 Nov is still 6 Nov in UTC; the ticket belongs
  to 7 Nov.
- Numbers are **never reused** within a day: cancelling, skipping, closing and
  reopening the queue all leave gaps instead of handing a number out twice.
- The ticket number is for display. Clients address tickets by their
  `public_id` (a random ULID), which reveals nothing about the order or the
  time of joining.

## Allocation

`App\Services\Queues\TicketNumberAllocator::next()` runs **inside the join
transaction**, after the session row is locked:

```sql
INSERT INTO queue_daily_counters (queue_id, business_date, last_number, …)
VALUES (?, ?, 1, …)
ON DUPLICATE KEY UPDATE last_number = last_number + 1;      -- SQLite: ON CONFLICT DO UPDATE

SELECT last_number FROM queue_daily_counters
WHERE queue_id = ? AND business_date = ? FOR UPDATE;
```

- The upsert is atomic: two transactions can never read the same value, and
  the first ticket of a day creates the row without a race.
- The row stays locked until the transaction commits or rolls back. On
  rollback the increment is undone too, so a failed join does not burn a
  number.
- A unique index on `queue_tickets (queue_id, business_date, sequence_number)`
  is the final guard: even a bug elsewhere could not store a duplicate.

What is deliberately **not** used: `SELECT MAX(sequence_number) + 1` (two
readers get the same value), Redis counters as the only source of truth, or
application-level locks.

## Business date of a ticket

A ticket takes the **session's** business date, not "now": a session that runs
past midnight keeps numbering under the day it was opened. The next session
opened on a new day starts at 1 (see
[QUEUE_LIFECYCLE.md](QUEUE_LIFECYCLE.md#day-roll-over)).

## Tests

- `CustomerJoinTest`: consecutive numbers, per-queue counters, no reuse after
  cancel/close/reopen, restart on the branch's next day, `BAR-1000`, database
  rejection of a duplicate sequence.
- `QueueConcurrencyTest` (MySQL/MariaDB): 16 separate processes join at the
  same instant and receive exactly 1…16.

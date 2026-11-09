# Wait-time estimation

Estimates are **approximate** and labelled as such everywhere (app, guest page).
They are computed by an implementation of `App\Contracts\WaitTimeEstimator`,
bound in `AppServiceProvider` (scoped per request). Since Phase 07 the default
is `SmartWaitTimeEstimator` (`app/Services/Intelligence`). It builds on the
service profile of `HistoricalWaitTimeEstimator`, the Phase 04 estimator,
which still exists and is still tested on its own. Both are deterministic
and cheap, and neither uses an external service.

## Service profile (historical)

```
seconds per customer =
    average of (completed_at − started_at) over the last N completed tickets
    of this queue, each clamped to 30 s … 4 h,
    when at least M such tickets exist                      → basis "history"
    otherwise: queue.estimated_service_duration
               ?? service.estimated_duration_minutes ?? 15 min → basis "configured"
quick / slow  = 25th / 75th percentile of the same recent times (history only)
assigned      = staff assigned to the queue, at least 1
```

`N` = `QUEUE_WAIT_HISTORY_SIZE` (20), `M` = `QUEUE_WAIT_HISTORY_MINIMUM` (5).
Clamping keeps one forgotten ticket (left "serving" for hours) or an instant
click-through from distorting the average.

## Smart estimate (default)

```
servers  = staff who called a ticket of the open session in the last
           DORY_AI_ACTIVE_STAFF_MINUTES (45) minutes; without any, the
           assigned staff; never fewer than the customers at the counter
at start = each customer at the counter keeps a server busy for
           (seconds per customer − time already served); called but not
           started: the whole service
then     = each person ahead takes the first free server
wait     = when the first server becomes free for the newcomer
range    = the same simulation with the quick and the slow service time
confidence = high (a full history window, or at least
             high_confidence_samples), medium (history), low (configured)
```

Example: one customer started 5 minutes ago in an 8-minute service, and one
person waits. A newcomer waits 3 + 8 = 11 minutes. The historical formula
ignored the customer at the counter and said 8.

The result keeps `seconds`, `minutes` and `basis`, and adds `confidence` and
`range {low_minutes, high_minutes}` (null without history). The customer
ticket shows "Usually 12 to 19 min" under the estimate when there is a range.

## Where it appears

| Place | Value |
|---|---|
| ticket (customer, guest API, guest page) | `estimated_wait` for the ticket's position |
| public queue info / join page / discovery | estimate for someone joining now |
| dashboard | estimate for a newcomer, `seconds_per_customer`, `servers` |
| `queue_tickets.estimated_wait_seconds` | estimate at join time (for later accuracy analysis) |

## Limitations

- Breaks, pauses and very irregular services make estimates rougher.
- "Active staff" is inferred from recent calls. A staff member who is
  present but has not called anyone in the window is not counted until
  they do.
- Priority customers who join later still go first; the estimate cannot
  know about them in advance.

The value is therefore always presented as "about N minutes".
`WaitTimeEstimationTest` covers the profile rules and
`SmartWaitTimeEstimatorTest` the smart estimate.

## Many queues at once

Lists call `WaitTimeEstimator::warm()` first. It loads the staff counts, the
last completed tickets of every queue (a window function) and the live state
of their open sessions in a fixed number of queries. `estimate()` then reads
from memory, and the result is identical to estimating one queue at a time,
which both tests check.

# Wait-time estimation

Estimates are **approximate** and labelled as such everywhere (app, guest page).
They are computed by an implementation of `App\Contracts\WaitTimeEstimator`,
bound in `AppServiceProvider` (scoped per request). The default is
`HistoricalWaitTimeEstimator`: deterministic, cheap, no machine learning. A
smarter estimator can replace the binding later without changing callers.

## Formula

```
seconds per customer =
    average of (completed_at − started_at) over the last N completed tickets
    of this queue, each clamped to 30 s … 4 h,
    when at least M such tickets exist                      → basis "history"
    otherwise: queue.estimated_service_duration
               ?? service.estimated_duration_minutes ?? 15 min → basis "configured"

servers   = number of staff assigned to the queue, at least 1
wait      = ceil(people ahead / servers) × seconds per customer
```

`N` = `QUEUE_WAIT_HISTORY_SIZE` (20), `M` = `QUEUE_WAIT_HISTORY_MINIMUM` (5).
People ahead = waiting tickets of the session that come first in queue order
(`priority DESC, id ASC`). Nobody ahead means an estimate of 0.

Clamping keeps one forgotten ticket (left "serving" for hours) or an instant
click-through from distorting the average.

## Where it appears

| Place | Value |
|---|---|
| ticket (customer, guest API, guest page) | `estimated_wait {seconds, minutes, basis}` for the ticket's position |
| public queue info / join page | estimate for someone joining now |
| dashboard | estimate for a newcomer, `seconds_per_customer`, `servers` |
| `queue_tickets.estimated_wait_seconds` | estimate at join time (for later accuracy analysis) |

## Limitations

- Managers serving without an assignment are not counted as servers.
- The remaining time of customers currently being served is not subtracted.
- Breaks, pauses and very irregular services make estimates rougher.

These are acceptable for an MVP and are why the value is always presented as
"about N minutes". `WaitTimeEstimationTest` covers each rule.

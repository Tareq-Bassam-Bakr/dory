# Business discovery

How customers find businesses without an account
(`app/Services/Discovery`, `app/Http/Controllers/Api/V1/Discovery`).

## What is public

Visibility uses the existing statuses. There is no approval workflow.

| Record | Public when |
|---|---|
| Business | `status = active`, not deleted, and at least one public branch |
| Branch | `status = active`, not deleted, and its business is active |
| Service | `status = active`, not deleted |
| Queue | `status = active`; its current session decides whether it is open |

`inactive`, `suspended` and `archived` businesses never appear. A business
page or branch page that is not public returns the same 404 as one that does
not exist, so the two cannot be told apart.

The scopes are `Business::publiclyVisible`, `Branch::publiclyVisible`,
`Service::publiclyVisible`, `Queue::openForJoining` and
`Queue::acceptingTickets`.

## What is returned

Public responses are built field by field (`PublicBusinessResource`,
`PublicBranchResource`, `QueueAvailabilityView`). A new column cannot appear in
them by accident, and they never carry members, owners, staff, internal ids,
audit data or anything about the customers in a queue. Queue data is counts
and rules only.

The contact details are the ones the business entered for customers: business
email, phone and website, and branch phone. Branch email is not returned.

## Live availability

`QueueAvailability` works out each queue's state from its **current** session:

| `status` | Meaning |
|---|---|
| `open` | Session open. `accepting_joins` is false when `is_full` |
| `paused` | Session paused: no new tickets |
| `closed` | No active session |
| `inactive` | Disabled, or its business, branch or service is not available; see `unavailable_reason` |

The same `Queue::unavailabilityReason()` drives the public views and the join
guard in `TicketJoinService`, so what a screen shows and what the server
enforces cannot disagree.

## Efficiency

- Every list is paged, with a maximum page size. Nothing loads all businesses.
- `DiscoveryCards` adds live numbers to a page in a fixed number of queries.
  Tests assert the query count does not grow with the page.
- `WaitTimeEstimator::warm()` loads the wait history of many queues in two
  queries (a window function, available on MySQL 8, MariaDB 10.2+ and SQLite
  3.25+), so estimates are not one query per queue.
- Indexes: `businesses (status, name)`, `(status, rating_average)`;
  `branches (status, country, city)`, `(status, latitude, longitude)`;
  `services (status, name)`.
- Rate limit: `RATE_LIMIT_DISCOVERY_PER_MINUTE` per IP (default 120).

## Configuration

| Variable | Default | Meaning |
|---|---|---|
| `DISCOVERY_DEFAULT_RADIUS_KM` | 10 | Radius when a location is sent without one |
| `DISCOVERY_MAX_RADIUS_KM` | 100 | Largest radius accepted |
| `DISCOVERY_MAX_PER_PAGE` | 50 | Largest page (the nearby list allows 100, for the map) |
| `DISCOVERY_WAIT_CANDIDATES` | 200 | Records compared when results depend on estimated waits |
| `DISCOVERY_MIN_RATINGS` | 3 | Real ratings needed before a rating is shown |

See [SEARCH_AND_FILTERS.md](SEARCH_AND_FILTERS.md) and
[CUSTOMER_API_REFERENCE.md](CUSTOMER_API_REFERENCE.md).

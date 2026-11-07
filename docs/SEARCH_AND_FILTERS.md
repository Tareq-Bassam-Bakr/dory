# Search and filters

All searching, filtering and sorting happens on the server
(`BusinessSearch`, `BranchSearch`, `AvailableQueues`). Flutter never receives
more than one page.

## Parameters

| Parameter | Meaning |
|---|---|
| `q` | Up to 100 characters; see below |
| `category` | Category slug. A parent includes its subcategories |
| `country` | ISO code, e.g. `SY` |
| `city` | Exact city of a branch |
| `service` | Part of a service name |
| `latitude`, `longitude` | Both or neither |
| `radius_km` | Default 10, maximum 100 |
| `open_queue` | Has a queue with an open session |
| `accepting` | Has a queue that would accept a ticket now (open and not full) |
| `max_wait` | Estimated wait of at most this many minutes |
| `min_rating` | Only businesses with enough real ratings |
| `sort` | `relevance` (default), `nearest`, `wait`, `rating` |
| `page`, `per_page` | Paging |

Invalid input is a 422: coordinates out of range, one coordinate without the
other, an oversized radius or page, `nearest` without a location, an unknown
sort.

## What `q` matches

The business name, its category in either language, and the name, city, region
or address of its public branches. Two letters also match a country code.
Inactive branches and services never match.

`%` and `_` typed by a customer are literal text. Search terms are always
bound as parameters, never written into SQL. The `!` escape character is used
because a backslash is written differently in MySQL and SQLite.

Category names are matched in PHP: there are few categories, and their JSON
is stored differently by each engine.

## Sorting

| Sort | Order |
|---|---|
| `relevance` | With `q`: exact name, then prefix, then contains. Then businesses with an open queue, then best rated, then name |
| `nearest` | Distance to the business's closest public branch. Needs a location |
| `rating` | Businesses with enough real ratings first, best first |
| `wait` | Lowest estimated wait |

### Sorting and filtering by wait

An estimated wait needs the estimator, not SQL. So `sort=wait` and `max_wait`
compare the first `DISCOVERY_WAIT_CANDIDATES` matches (200) in PHP, in a fixed
number of queries, and page the result by hand. Consequences:

- Only businesses with a queue accepting tickets are listed: a closed queue
  has no wait.
- With more than 200 matches, some may not be considered. Combining a
  category or a location keeps the set small.

## Limits of this search

`q` uses `LIKE '%term%'`, which an index can only partly help. That is fine at
this scale and avoids new infrastructure. Arabic text is matched as typed:
letter variants such as أ and ا are different, and there is no stemming or typo
tolerance. If the catalogue outgrows this, full-text search belongs behind
`BusinessSearch`, without changing the API.

## In the app

- Typing waits 350 ms before searching.
- Every new search cancels the request in flight (`ApiCancellation`), and a
  generation counter discards any late answer, so an old result can never
  replace a newer one.
- More results load near the end of the list, one page at a time.
- Recent searches stay on the device and can be cleared.

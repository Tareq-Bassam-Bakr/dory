# Phase 05 report: customer experience, discovery, maps and digital tickets

Date: 2026-11-07 · Baseline: tag `phase-04-baseline` → `1d1745e` ·
Final commit: see [Commits](#commits).

## Summary

DORY now has a complete customer side, built on the Phase 04 queue engine.
Anyone can open the app and browse without an account: search and filter
businesses on the server, find what is nearby, use an OpenStreetMap map, and
see live queue availability. They can join a queue signed in or as a guest,
follow a digital ticket, leave the queue, keep a history, save businesses and
rate a completed visit. Owners and staff keep their business workspace and
switch between the two.

**Not done:** the real Android device test. The phone was connected at the
start of the phase, then disconnected and did not return. The app was verified
in other ways, listed [below](#android-device), but it has **not** been run on
a phone in this phase. Real-time updates remain out of scope; open tickets poll
and say so.

## Delivered, step by step

| Step | Result |
|---|---|
| A. Inspect and baseline | Repository, reports, API and tests reviewed. Annotated tag `phase-04-baseline` on `1d1745e`, pushed |
| B. API review | Existing endpoints reused; gaps listed in [CUSTOMER_API_REFERENCE.md](CUSTOMER_API_REFERENCE.md) |
| C, D. Discovery, search, filters, nearby | `/discovery/*`, all server-side and paged, in a fixed number of queries |
| E. Business, branch, service details | Public pages; only public records; field-by-field resources |
| F. Navigation | Home, Explore, My Tickets, Favorites, Profile; workspace switch for owners |
| G. Home, discovery, search | Live sections that fail independently; debounced, cancellable search; recent searches |
| H. Map and location | flutter_map with a configurable tile provider; location on request only, with a manual city fallback |
| I. Branch and service selection | Live queue state, wait, capacity, whether guests are allowed; a message for every blocked state |
| J. Authenticated joining | Existing API, with an idempotency key |
| K. Guest joining | Only the fields the queue requires; same service and token contract as Phase 04 |
| L. Digital ticket | All eight states; finished tickets offer no ordinary actions |
| M. Guest persistence | Token in secure storage only; survives a restart; missing credentials handled |
| N. Polling and history | Controlled polling with backoff, only while on screen; paged history |
| O. Favorites and profile | Server favorites, device-local for visitors; phone, editing, activity links |
| P. Reviews | The backend had none, so a minimal version was added: one rating per completed ticket, no text, no generated data |
| Q. Security and tests | See [Security findings](#security-findings) and [Test results](#test-results) |
| R. Android device | **Not performed**; see [Android device](#android-device) |
| S. Documentation and commits | 11 documents, README, commits pushed and verified |

## Customer screens

Home, Explore, My Tickets, Favorites, Profile, Map, Business, Branch and
services, Join queue, Digital ticket, Edit profile, and the location and filter
sheets. All use the design system, in English and Arabic, with 153 new strings.

## API

**Created:** `GET /discovery/businesses`, `/discovery/businesses/{slug}`,
`/discovery/businesses/{slug}/branches`, `/discovery/nearby`,
`/discovery/queues`, `/discovery/cities`, `/discovery/branches/{id}`,
`/discovery/branches/{id}/services`; `GET` and `POST /public/queues/{id}[/join]`
(by queue id); `GET`, `POST`, `DELETE /me/favorites`; `POST /tickets/{id}/review`.

**Reused:** `/business-categories`, `/queues/{id}/join`, `/tickets/{id}/position`,
`/tickets/{id}/cancel`, `/me/tickets` (with new filters), `/public/queues/{token}`,
`/public/tickets/*`, `/users/profile`.

Some paths differ from the plan's suggestions, because `/businesses/{id}` and
`/branches/{id}` already exist as member-only routes. The reasons are in
[CUSTOMER_API_REFERENCE.md](CUSTOMER_API_REFERENCE.md#changes-from-the-phase-05-plan).

## Main changed files

| Area | Files |
|---|---|
| Discovery | `backend/app/Services/Discovery/*`, `Http/Controllers/Api/V1/Discovery`, `Http/Requests/Discovery`, `PublicBusinessResource`, `PublicBranchResource` |
| Queue engine | `TicketJoinService` (status guard, join source), `HistoricalWaitTimeEstimator` (`warm`), `PublicQueueController`, `PublicQueueResource`, `CustomerTicketResource` |
| Customers | `FavoriteController`, `TicketReviewService`, `TicketReview` |
| Database | Four migrations of 2026-11-07: discovery columns and indexes, `customer_favorites`, `ticket_reviews`, timestamp fix. `CustomerDemoSeeder` |
| Flutter | `lib/features/{discovery,maps,tickets,favorites,home,profile}`, `lib/core/location`, `lib/core/config/{map,ticket}_config.dart`, router |
| Platform | Android manifest, iOS `Info.plist`, `pubspec.yaml` |

New packages: `flutter_map`, `latlong2`, `geolocator`, `url_launcher`.

## Test results

All from the final run on 2026-11-07, on the development machine.

| Suite | Result |
|---|---|
| Backend, MySQL 8.4.13 (`composer test:mysql`, local) | ✅ **576 passed** (5115 assertions), including all 8 concurrency tests |
| Backend, MariaDB 10.4 (`composer test:mariadb`) | ✅ **576 passed** (5115 assertions), including all 8 concurrency tests |
| Backend, SQLite (`composer test`) | ✅ **567 passed, 9 skipped** (4923 assertions). The 9 need MySQL or MariaDB |
| Pint | ✅ passed |
| Flutter (`flutter test`) | ✅ **499 passed** |
| Flutter analyzer | ✅ no issues |
| `dart format` | ✅ no changes |
| GitHub Actions | ⚠️ not run: it still does not start for this repository |

Phase 05 added 106 backend tests (470 → 576) and 179 Flutter tests (320 → 499).
No existing test was removed or weakened. Four existing test files changed:

- `RouteProtectionTest`: its explicit list of public routes gained the new ones.
- `app_router_test.dart`: tab labels updated, and it now visits all five tabs.
- `layout_smoke_test.dart`: extended to every customer screen.
- Test helpers: new optional parameters.

Every migration is rolled back and re-run in `MigrationRollbackTest` on each
engine, including the four new ones.

### Checks beyond the test suites

- **Real API contract.** The real Flutter repositories talked over HTTP to a
  running Laravel server on `dory_db_testing` (temporary test, not committed):
  discovery, Arabic search, nearby, guest join with the real token, a wrong
  token refused, retries, phone rules, a queue without guests, customer join,
  duplicate refused, history, cancellation and favorites. All passed.
- **Real rendering.** Because the phone was unavailable, a temporary web build
  ran in a real browser at phone size against the same API, with real
  OpenStreetMap tiles: home, location picker, map, marker preview, guest join,
  ticket, cancellation, a true page reload, and Arabic RTL. The `web/` folder
  was removed afterwards. This found a real bug the 562 tests had missed (next
  section).
- **Android build.** `flutter build apk --debug` succeeded with the new plugins.
  The merged manifest has only `INTERNET` and coarse and fine location.

## Android device

**Not performed.** Phone `151923757C004111` was listed by `adb` at the start of
the phase, then disappeared and did not return, even after restarting `adb`.

`integration_test/phase05_e2e_test.dart` is written to walk the guest journey on
a device, including a cold restart, but **it has never been run**, so it may
need adjusting. These behaviours are therefore unverified on a real phone:
secure storage on Android, the system location prompt, and map performance.
`CustomerDemoSeeder` provides data for the test
([steps](CUSTOMER_EXPERIENCE.md#testing-on-a-device)).

## Security findings

Details and tests are in [CUSTOMER_SECURITY.md](CUSTOMER_SECURITY.md). Found and
fixed during this phase:

| Finding | Severity | Fix |
|---|---|---|
| A queue kept accepting joins after its business, branch or service was deactivated (Phase 04 gap) | High | Guard in `TicketJoinService`, shared with the public views |
| On MariaDB, `joined_at`, `opened_at`, `assigned_at` and an invitation's `expires_at` were rewritten on any update of their row (Phases 03 and 04). MySQL 8.4 is not affected | High on MariaDB | Migration and `TimestampColumnsTest`, which failed on MariaDB before the fix |
| `GET /public/queues/{id}` would have shown a suspended business's name and address | Medium | 404 unless the business is public |
| Two taps in the same frame sent two join requests (no duplicate ticket: the key prevented it) | Low | Synchronous guard |
| Precise coordinates in query strings could reach access logs | Low | Rounded to about 100 m in the app |
| Riverpod retried failed requests in the background, including 404s | Low | Automatic retry off for API providers |
| Strings promised notifications and "live" positions that do not exist | Low | Rewritten |
| `INTERNET` declared only in the debug manifest, so a release build could not reach the API | High for release | Added to the main manifest |

Verified: no token in URLs, logs, SharedPreferences or screens; tickets and
favorites scoped to their owner; no membership or customer data in public
responses; no background location; rate limits on every route.

## Map provider configuration

Debug and profile builds use `tile.openstreetmap.org`, for light development use
only. **Release builds have no default:** without `DORY_MAP_TILE_URL` they show
the list instead of a map, so production traffic can never reach the public
server by accident. Attribution is always visible and tappable. Setup:
[OSM_TILE_PROVIDER.md](OSM_TILE_PROVIDER.md).

**You will need to choose a tile provider before release.**

## Known limitations

1. **Not tested on a phone** in this phase (see above).
2. **No real-time updates or notifications.** Open tickets poll about every 15
   seconds, only while on screen, so a customer not looking at the app can miss
   being called.
3. **Guest tickets are not linked to accounts** created later. This is stated in
   the app.
4. **Search** uses `LIKE`: no typo tolerance, Arabic letter variants differ, and
   it will need full-text search at a much larger scale.
5. **Sorting or filtering by wait** compares the first 200 matches.
6. **No opening hours** exist in the backend, so none are shown.
7. **No business instructions** on tickets: the backend has no such field.
8. **Business logos and covers** display when present, but there is still no
   upload feature, so they are usually the category icon.
9. **Ratings** cannot be changed once given, and have no text.
10. **No dark theme**, because the theme architecture has none.
11. **Guests** are limited per IP and by capacity, as in Phase 04.
12. **Coordinates** are in query strings, as the plan specified; production
    access logs should drop them for discovery routes.
13. **MySQL 8.4 is verified locally only**, because GitHub Actions does not
    start.

## Local changes outside the repository

- The local development database `dory_db` gained the four new migrations
  (`php artisan migrate`). It still has no users or businesses.
- `dory_db_testing` holds whatever the last test run left.
- The local MySQL 8.4 test server is stopped.

## Commits

All commits are authored and committed by **Tareq Bassam Bakr
<255685054+Tareq-Bassam-Bakr@users.noreply.github.com>**, with no co-author line.
Each push was verified (`git rev-parse HEAD` = `gh api .../commits/main`). No
force-push, no history rewrite. The repository remains private.

| Commit | Content |
|---|---|
| `e80d5d2` | Public discovery, favorites and ratings API (C, D, E, P) |
| `3533612` | Customer app: discovery, map, joining, tickets (F to O) |
| *(this commit)* | Timestamp fix, security fixes, demo data, device test, documentation (Q, S) |

## Remaining work

1. Run the device test on a phone (`integration_test/phase05_e2e_test.dart`).
2. Choose a production tile provider.
3. Fix the GitHub Actions account issue, so MySQL 8.4 runs in CI.
4. Phase 06 (real-time updates and notifications), when you approve it.

## Next

Phase 06 has **not** been started and will not be without your explicit
approval.

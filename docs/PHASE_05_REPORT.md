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

**Android device:** tested on a real phone (TECNO KM7, Android 15, Arabic) once
it was connected again. The three on-device tests pass, and a walk through the
real app found two bugs the test suites had missed, both fixed with tests
([details](#android-device)). Real-time updates remain out of scope; open
tickets poll and say so.

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
| R. Android device | Done on a TECNO KM7 (Android 15, Arabic): three device tests pass, two bugs found and fixed; see [Android device](#android-device) |
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

All from 2026-11-07, on the development machine. The backend suites ran on the
final backend code, which the phone fixes did not touch; the Flutter checks ran
again after them.

| Suite | Result |
|---|---|
| Backend, MySQL 8.4.13 (`composer test:mysql`, local) | ✅ **576 passed** (5115 assertions), including all 8 concurrency tests |
| Backend, MariaDB 10.4 (`composer test:mariadb`) | ✅ **576 passed** (5115 assertions), including all 8 concurrency tests |
| Backend, SQLite (`composer test`) | ✅ **567 passed, 9 skipped** (4923 assertions). The 9 need MySQL or MariaDB |
| Pint | ✅ passed |
| Flutter (`flutter test`) | ✅ **509 passed** |
| On-device tests (`flutter test integration_test`, TECNO KM7) | ✅ **3 passed**: app launch, the Phase 03 journey, the Phase 05 guest journey |
| Flutter analyzer | ✅ no issues |
| `dart format` | ✅ no changes |
| GitHub Actions | ⚠️ not run: it still does not start for this repository |

Phase 05 added 106 backend tests (470 → 576) and 189 Flutter tests (320 → 509).
No existing test was removed or weakened. Six existing test files changed:

- `RouteProtectionTest`: its explicit list of public routes gained the new ones.
- `app_router_test.dart`: tab labels updated, and it now visits all five tabs.
- `layout_smoke_test.dart`: extended to every customer screen.
- Test helpers: new optional parameters.
- `app_launch_test.dart` and `phase03_e2e_test.dart` (on-device): the customer
  tabs are now called My tickets and Profile, so these tests looked for labels
  that no longer exist. Nothing ran them until the phone was back. The labels
  were updated; every check is unchanged.

Every migration is rolled back and re-run in `MigrationRollbackTest` on each
engine, including the four new ones.

### Checks beyond the test suites

- **Real API contract.** The real Flutter repositories talked over HTTP to a
  running Laravel server on `dory_db_testing` (temporary test, not committed):
  discovery, Arabic search, nearby, guest join with the real token, a wrong
  token refused, retries, phone rules, a queue without guests, customer join,
  duplicate refused, history, cancellation and favorites. All passed.
- **Real rendering.** Before the phone was back, a temporary web build
  ran in a real browser at phone size against the same API, with real
  OpenStreetMap tiles: home, location picker, map, marker preview, guest join,
  ticket, cancellation, a true page reload, and Arabic RTL. The `web/` folder
  was removed afterwards. This found a real bug the 562 tests had missed (next
  section).
- **Android build.** `flutter build apk --debug` succeeded with the new plugins.
  The merged manifest has only `INTERNET` and coarse and fine location.

## Android device

Phone: TECNO KM7, Android 15, device language Arabic, debug build, connected by
USB with `adb reverse` to a local API on the throwaway database
`dory_db_testing`.

**On-device tests** (`flutter test integration_test`): all three pass.

| Test | What it does on the phone |
|---|---|
| `app_launch_test.dart` | Splash, welcome, guest shell |
| `phase03_e2e_test.dart` | Registers, creates a business through onboarding, restarts, session restored |
| `phase05_e2e_test.dart` | Guest browses, joins, the app starts again with the ticket and token read back from device storage, leaves the queue |

The first Phase 05 run failed inside the test, not the app: it tapped the
sign-in card above the ticket. Fixed, with the test's error handling corrected
so a failure shows its real message.

**Walk through the real app**, comparing values between screens:

| Checked | Result |
|---|---|
| Location prompt | Appears only after "Use my location". Refusing it shows a clear message and the city list; picking a city works. Allowing it ("only this time", approximate) gives "Near me" and distances. No background location permission exists |
| Map | Real OpenStreetMap tiles, markers, preview card, panning, recentring. The attribution is visible and opens the OpenStreetMap copyright page |
| Guest ticket | Joined, then the app process was killed (`am force-stop`). After starting again, the ticket and its token came back, with the same number and join time |
| Storage | Plain storage holds only the guest ticket list (no token field, no coordinates). The token is only in Android's encrypted storage |
| Polling | Every 15 seconds on the ticket screen, none while the app is in the background, an immediate refresh on return, and none after the ticket finishes |
| Staff actions | Called, serving and completed (done through the queue service on the server) each reached the phone at the next poll. The join time never changed |
| Account | Sign-up, session kept across a process kill, favorites, a signed-in join, and a rating: four stars on the phone, `4` stored, the business average updated |
| Arabic | Right-to-left layout throughout, including the stars |

**Found and fixed:**

1. **A saved business appeared twice in Favorites** (the server had one row).
   The list's first load and the reload after saving both fetched page 1 and
   both added their results. My Tickets had the same race on a refresh during
   its first load. Only the latest load may now fill a list, and a page can no
   longer be fetched twice at once. `paged_lists_test.dart` reproduces both
   races and failed before the fix. Seen fixed on the phone.
2. **Distances read in the wrong order** in Arabic with a Latin city name:
   "1.1" was separated from "km" by "Damascus". The city and the distance are
   now isolated for text direction (`discovery_labels_test.dart`). Seen fixed
   on the phone.
3. **The welcome screen promised to "follow your turn live"**, which the app
   does not do. Reworded.

**Not covered on the phone:** a release build (it needs a tile provider and an
API address), behaviour without a network, and performance figures (a debug
build says nothing about them).

**Test environment notes.** A full run once failed because the USB link
dropped for a moment and `adb reverse` was lost, so the app could not reach the
API; another failed because the PHP development server took more than 30
seconds to load framework files right after a Gradle build. Both were the
machine, not the app: the same tests passed after restoring the forwarding and
once the load was gone. During the walkthrough, three taps meant for the app
reached the phone's launcher and Chrome after the app closed; they changed
nothing, and input is now sent only while DORY is in front.

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
| Strings promised notifications and "live" positions that do not exist (one more on the welcome screen, found on the phone) | Low | Rewritten |
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

1. **Phone testing used a debug build** on one Android phone; no release build
   and no iPhone (see above).
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
14. **Text written by a business in another script** follows the page
    direction: in Arabic, an English description shows its final period on
    the left.
15. **The on-device tests need fresh test data** (a public queue that accepts
    guests, as `CustomerDemoSeeder` provides) and an active `adb reverse`.

## Local changes outside the repository

- The local development database `dory_db` gained the four new migrations
  (`php artisan migrate`). It still has no users or businesses.
- `dory_db_testing` was emptied after the phone tests (fresh schema, no rows).
- The local MySQL 8.4 test server is stopped; nothing listens on port 8000;
  `adb reverse` was removed.
- The phone has a fresh install of the final debug build, with no data and no
  permission granted. Running the on-device tests uninstalls the app, which
  removed the previous install and its data.

## Commits

All commits are authored and committed by **Tareq Bassam Bakr
<255685054+Tareq-Bassam-Bakr@users.noreply.github.com>**, with no co-author line.
Each push was verified (`git rev-parse HEAD` = `gh api .../commits/main`). No
force-push, no history rewrite. The repository remains private.

| Commit | Content |
|---|---|
| `e80d5d2` | Public discovery, favorites and ratings API (C, D, E, P) |
| `3533612` | Customer app: discovery, map, joining, tickets (F to O) |
| `e832a88` | Timestamp fix, security fixes, demo data, device test, documentation (Q, S) |
| *(this commit)* | Phone testing (R): the two bugs it found fixed with tests, device tests updated, report |

## Remaining work

1. Choose a production tile provider.
2. Fix the GitHub Actions account issue, so MySQL 8.4 runs in CI.
3. Test a release build on a phone once a tile provider and API address exist.
4. Phase 06 (real-time updates and notifications), when you approve it.

## Next

Phase 06 has **not** been started and will not be without your explicit
approval.

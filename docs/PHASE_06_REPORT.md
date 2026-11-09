# Phase 06 report: real-time engine, notifications and one-click startup

Date: 2026-11-08 to 2026-11-09 · Baseline: tag `phase-05-baseline-for-06` → `f7a8716` ·
Final commit: see [Commits](#commits).

## Summary

Queue changes now reach open tickets and staff screens at once, through
Laravel Reverb running on this machine at no cost. The 15-second polling
remains as the fallback, and the ticket and queue screens say which mode
they are in.
Signed-in users have a notification center with preferences. Customers
and staff are told about the changes that matter to them, once each, and
in English or Arabic. Push notifications through Firebase Cloud Messaging
are **implemented and off**: no Firebase project exists, so live push delivery is
**pending** and is not claimed. `start_dory.bat` starts the API, Reverb, the
queue worker and the app with one double-click; `stop_dory.bat` stops
exactly those.

**Phone:** tested on the TECNO KM7 (Android 15, Arabic) against the real
API, Reverb and worker on the throwaway database:

- a staff call reached an open ticket in **0.75 to 1 s**;
- a new customer reached the staff dashboard in **0.9 to 1.6 s**;
- the five on-device tests pass.

All 13 checks from your list ran except live FCM, which needs Firebase. The
phone found **six bugs** that 639 backend and 572 Flutter tests had missed,
all fixed with tests ([Found on the phone](#found-on-the-phone)).

## Delivered, step by step

| Step | Result |
|---|---|
| A. Inspect | Repository, Phase 05 report, `.env`, databases and suites reviewed. `dory_db` had 0 users and 0 businesses |
| B. Baseline | Annotated tag `phase-05-baseline-for-06` on `f7a8716`, pushed |
| C. Reverb | `laravel/reverb` 1.12, configured from `.env`. Client events off. Single server, no Redis ([REVERB_SETUP.md](REVERB_SETUP.md)) |
| D. Channels | `private-users`, `private-tickets`, `private-queue-sessions`, `private-businesses`, plus the public `queue-updates.{session}` for guests. Rules read the database; the channel name is never trusted ([BROADCAST_CHANNELS.md](BROADCAST_CHANNELS.md)) |
| E. Queue transitions | Every recorded queue change (`QueueActivity`, after commit) broadcasts `queue.changed`, `ticket.updated` and `queue.updated`; queue setting changes too. Payloads are ids and statuses only |
| F. Workers | Database queue with `after_commit`. Queues `realtime`, `notifications`, `default`; retries with backoff |
| G. Notification storage | `notifications`, `notification_preferences` and `notification_dispatches` (sends each alert at most once) |
| H. FCM backend | `kreait/laravel-firebase` 7.2 (HTTP v1). `FirebasePushSender` maps FCM answers to accepted, invalid token, retry or failed. It stays off unless `FCM_ENABLED=true` and the credential file is readable |
| I. Device tokens | `push_devices`: token encrypted, looked up by hash, never returned or logged. Registration for users and for guest tickets (ticket token in a header) ([DEVICE_TOKEN_SECURITY.md](DEVICE_TOKEN_SECURITY.md)) |
| J. Flutter Reverb client | `PusherClient`, our own Pusher protocol 7 client on `web_socket_channel` (one WebSocket package). Shared connection, counted subscriptions, private channel auth, ping, reconnect 1 to 30 s, background suspend |
| K. Reverb-first tickets | `LiveTicketSync` and `AutoRefresh`: no timer while live, one read per event burst, one read after reconnecting, polling when not live. The API stays the source of truth |
| L. Flutter FCM | `firebase_messaging` 16.7 behind an interface. Foreground, background, opened and initial messages, background handler, token refresh. Off unless built with `DORY_FCM_ENABLED=true` |
| M. Notification center | Bell with badge, Notifications (filters, paging, mark all read), Notification settings (permission, six preferences) ([NOTIFICATION_CENTER.md](NOTIFICATION_CENTER.md)) |
| N. Who is told what | Customers, guests and staff ([NOTIFICATION_EVENTS.md](NOTIFICATION_EVENTS.md)). No alert for position changes, except "almost your turn" once |
| O. Phone | ✅ on the TECNO KM7: 5 on-device tests, 12 of 13 checks (FCM needs Firebase), 6 bugs found and fixed ([Android device](#android-device)) |
| P, Q. Startup | `start_dory.bat` / `stop_dory.bat` with `scripts/dev/*.ps1`, tested on this machine ([Startup acceptance](#startup-acceptance)) |
| R. Suites | All green ([Test results](#test-results)) |
| S. Documentation | Nine new documents and this report; README, ticket experience and MySQL status updated |
| T. Commits | Pushed and verified ([Commits](#commits)) |

## API

| Endpoint | |
|---|---|
| `POST /api/v1/broadcasting/auth` | private channel signatures (Sanctum, active account) |
| `GET /api/v1/me/notifications`, `POST …/{id}/read`, `POST …/read-all` | notification center |
| `GET`, `PUT /api/v1/me/notification-preferences` | preferences |
| `POST /api/v1/devices/register`, `DELETE /api/v1/devices/{device}/unregister` | signed-in push devices |
| `POST /api/v1/public/tickets/{ticket}/devices/register`, `DELETE …/devices/unregister` | guest push devices (`X-Ticket-Token`) |

`CustomerTicketResource` gained `realtime.public_channel` and
`realtime.private_channel` (null for guests). The app accepts only the two
expected prefixes.

## Main changed files

| Area | Files |
|---|---|
| Real-time (API) | `config/{reverb,broadcasting,queue}.php`, `routes/channels.php`, `bootstrap/app.php`, `app/Events/Realtime/*`, `app/Realtime/QueueActivityRelay.php` |
| Notifications (API) | `app/Notifications/*`, `app/Services/Notifications/*`, `app/Jobs/{ProcessQueueNotifications,SendPushMessage}.php`, `app/Http/Controllers/Api/V1/Notifications/*`, `lang/{en,ar}/notifications.php` |
| Push (API) | `app/Services/Push/*`, `app/Models/PushDevice.php`, `config/firebase.php` |
| Database | Four migrations of 2026-11-08: `notifications`, `notification_preferences`, `push_devices`, `notification_dispatches` |
| Flutter | `lib/core/realtime/*`, `lib/core/notifications/*`, `lib/core/config/{realtime,push}_config.dart`, `lib/features/notifications/*`, `live_ticket_sync.dart`, `AutoRefresh` |
| Android | Desugaring, `POST_NOTIFICATIONS`, default channel and icon, Google services plugin applied only with `google-services.json`, settings shortcut in `MainActivity.kt` |
| Startup | `start_dory.bat`, `stop_dory.bat`, `scripts/dev/{start_dory,stop_dory}.ps1`, `scripts/dev/dory.local.example.psd1` |

New packages: API `laravel/reverb`, `kreait/laravel-firebase`. Flutter
`web_socket_channel`, `firebase_core`, `firebase_messaging`,
`flutter_local_notifications`. Reverb requires `guzzlehttp/psr7` 2.x, so
Guzzle went from 8.3 to 7.16; Laravel 13 accepts both, and DORY does not call
Guzzle itself.

## Real-time results

- **Unit and feature tests:** channel rules through the real auth endpoint,
  with the Reverb signature checked (`BroadcastChannelsTest`). Payloads and
  after-commit behaviour (`QueueBroadcastsTest`). The Flutter client against
  a fake Reverb: subscription, auth, reconnection, ping, error codes, and
  suspend and resume (`pusher_client_test`). Screens live and polling
  (`live_ticket_sync_test`, `realtime_screens_test`).
- **Against the real Reverb server** (temporary, on `dory_db_testing`): a
  public channel received `queue.changed` after a staff call. A signed-in
  customer received `ticket.updated` on their private channel. A user from
  another business was refused `private-queue-sessions` with 403. The
  notification center returned the alert in Arabic.
- **With `start_dory.bat`:** the script's own check opens a real WebSocket
  and requires `pusher:connection_established` for the app key.
- **On the phone:** a staff call reached an open guest ticket in 0.75 to
  1 s, and "start" and "complete" in about 1.5 s. A new customer reached
  the staff dashboard in 0.9 to 1.6 s. With Reverb stopped, the ticket
  switched to the polling note within 6 s and still caught a call in 10.6 s.
  After Reverb came back, it was live again within the 30 s retry ceiling
  ([Android device](#android-device)).

## FCM status

| Part | Status |
|---|---|
| Backend sending (HTTP v1), error mapping, retries, token revocation | ✅ implemented, tested with mocks (`FirebasePushSenderTest`, `QueueNotificationsTest`) |
| Device registration, rotation, sign-out removal, guest registration | ✅ implemented, tested (API and Flutter) |
| Flutter foreground, background, opened and initial messages, taps | ✅ implemented, tested with a fake `PushMessaging` |
| Android: permission, channel, icon, conditional Google services plugin | ✅ built (`flutter build apk --debug`) |
| Firebase project, `google-services.json`, service account | ⛔ **not configured**. Needs your Google account ([FIREBASE_SETUP.md](FIREBASE_SETUP.md)) |
| **Live push delivery to a phone** | ⏳ **pending**: not tested, not claimed |
| iOS (APNs) | ⏳ not configured, not claimed |

Until Firebase is configured, closed apps get no alert. Signed-in users find
everything in the notification center, and open apps get a local
notification through Reverb.

## Test results

All on 2026-11-09, on this machine, on the final code (after the phone
fixes).

| Suite | Result |
|---|---|
| Backend, MySQL 8.4.13 (`composer test:mysql`, local) | ✅ **640 passed** (5562 assertions), including all 8 concurrency tests and the full migration rollback |
| Backend, MariaDB 10.4 (`composer test:mariadb`) | ✅ **640 passed** (5562 assertions), including all 8 concurrency tests |
| Backend, SQLite (`composer test`) | ✅ **631 passed, 9 skipped** (5370 assertions). The 9 need MySQL or MariaDB |
| Pint | ✅ passed |
| Flutter (`flutter test`) | ✅ **576 passed** |
| Flutter analyzer | ✅ no issues |
| `dart format` | ✅ no changes |
| On-device tests (`flutter test integration_test`, TECNO KM7) | ✅ **5 passed** in one run: app launch, the Phase 03 journey, the Phase 05 guest journey, and the two new real-time tests |
| GitHub Actions | ⚠️ not run: it still does not start for this repository (account-level) |

Phase 06 added 64 backend tests (576 → 640), 67 Flutter tests (509 → 576)
and 2 on-device tests (3 → 5). No existing test was removed or weakened.
Changed existing tests:

- `RouteProtectionTest`: its explicit list of public routes gained the two
  guest device routes.
- `customer_screens_test.dart`: Phase 05's "notifications are an honest
  placeholder" checked that the bell said notifications would come later.
  They exist now, so the test became "the bell opens the notification
  center".
- Test helpers gained optional parameters (notifier, push, overrides).
- `InMemoryKeyValueStorage`, the storage tests use, now accepts only the
  keys in `StorageKeys.all`, as the phone's storage does. That is how bug 1
  below slipped through; with the check, 18 ticket tests fail on the old
  code.

The backend areas you listed map to: broadcasting events and queue
transitions (`QueueBroadcastsTest`); channel authorization and multi-tenant
security (`BroadcastChannelsTest`); guest privacy (both); notifications,
failed handling and duplicate protection (`QueueNotificationsTest`,
`NotificationCenterTest`); device tokens (`PushDevicesTest`); FCM mocked
(`FirebasePushSenderTest`). The Flutter areas map to `pusher_client_test`,
`live_ticket_sync_test`, `realtime_screens_test`,
`notification_coordinator_test`, `notification_center_test` and
`notification_target_test`.

## Startup acceptance

Tested on Windows 11 with the real paths: PHP `D:\php84`, XAMPP MariaDB on
3306, `backend/.env` on `dory_db`.

| # | Check | Result |
|---|---|---|
| 1 | `start_dory.bat` starts the API | ✅ DORY-API window |
| 2 | The API health endpoint responds | ✅ `/up` checked by the script |
| 3 | Reverb starts and accepts a connection | ✅ WebSocket handshake answered `pusher:connection_established` |
| 4 | Queue worker starts | ✅ DORY-QUEUE window |
| 5 | Flutter starts on a connected device | ✅ `start_dory.bat` found the phone, set `adb reverse` for 8000 and 8080, and DORY-FLUTTER built and launched DORY on it (about 3 min). The app loaded `dory_db`'s empty state from that API |
| 6 | The app authenticates against the local server | ✅ on the throwaway database, with the same commands the script runs: sign-in through the real login screen (device test, and by hand). Not on `dory_db`: you chose to keep it free of test accounts |
| 7 | Real-time updates work on the device | ✅ same setup as 6 ([Android device](#android-device)) |
| 8 | `stop_dory.bat` stops only DORY processes | ✅ a decoy PHP process and MariaDB (`mysqld` 4616) survived; ports 8000 and 8080 freed; run state removed |
| 9 | XAMPP MariaDB remains unaffected | ✅ same process before and after, never touched |
| 10 | A second start does not duplicate | ✅ "already running" for each service; PHP process count unchanged (4 → 4) |
| extra | A port held by another program | ✅ the start refused, naming the process; nothing started or stopped |
| extra | Pending migrations | ✅ listed with a `[y/N]` question; answering no left `dory_db` unchanged |
| extra | A service that stopped inside its window | ✅ added after the worker died on a lost database connection during the phone test. The next start closed the dead DORY-QUEUE window and started a new worker, reusing the API and Reverb |
| extra | Stop after a Flutter run | ✅ stopped DORY-API, DORY-REVERB, DORY-QUEUE and DORY-FLUTTER and removed `adb reverse`. The adb server and MariaDB kept running |

### Instructions

1. Start MySQL in the XAMPP Control Panel.
2. Connect the phone by USB (USB debugging on).
3. Double-click `start_dory.bat`. On the first run after this phase, it lists
   the four Phase 06 migrations and asks before running them on `dory_db`.
4. Use the app. `r` / `R` in DORY-FLUTTER reload it.
5. Double-click `stop_dory.bat` when done.

Everything else, including options, the safety rules and troubleshooting:
[LOCAL_DEVELOPMENT_STARTUP.md](LOCAL_DEVELOPMENT_STARTUP.md).

## Android device

TECNO KM7, Android 15, Arabic. The API, Reverb and a queue worker ran on
the throwaway database `dory_db_testing`, with demo data and random
passwords kept outside the repository. `dory_db` was not touched. Times
measured by hand include 2 to 3 s per screen read over adb. The automated
times are exact.

| # | Test | Result |
|---|---|---|
| 1 | DORY opens normally | ✅ every launch, including after a force stop and from `start_dory.bat` |
| 2 | Customer joins a queue | ✅ as a guest (name required) and signed in, in Arabic |
| 3 | Staff calls the customer | ✅ through the API (device test) and the staff service |
| 4 | Ticket updates at once through Reverb while visible | ✅ call in 0.75 to 1 s; start and complete in about 1.5 s; no polling while live |
| 5 | Reverb disconnects and reconnects | ✅ Reverb stopped: polling note within 6 s, and a call still arrived by polling in 10.6 s. Reverb back: live again in 32.8 s (the retry delay tops out at 30 s), then live updates |
| 6 | App goes into the background | ✅ the connection closes (suspended); on return the ticket showed the change made meanwhile within 2.5 s, and the badge was read again (5 → 6) |
| 7 | FCM notification arrives | ⛔ cannot run: no Firebase project. In the background, nothing arrives, as documented, and the alert waits in the notification center |
| 8 | Customer taps notification | ✅ local notification shown through Reverb while DORY was open on another screen |
| 9 | DORY opens the correct ticket | ✅ and Back now returns to DORY (bug 4) |
| 10 | Reopened after a process restart | ✅ `am force-stop`, then relaunch |
| 11 | Guest ticket can still be accessed securely | ✅ an active guest ticket reopened from secure storage after the force stop, went live in 2.5 s and showed the next call |
| 12 | Business dashboard receives queue changes | ✅ staff signed in through the login screen; a new customer appeared in 0.9 to 1.6 s |
| 13 | Arabic notification display | ✅ "حان دورك", "اكتملت الزيارة" and "بدأت خدمتك" in the shade and in the notification center |

On-device tests: `flutter test integration_test`, all 4 files in one run,
**5 passed** ([REALTIME_ARCHITECTURE.md](REALTIME_ARCHITECTURE.md#testing-on-a-device)).

The notification permission was granted by you on the phone's own dialog.
The phone's lock and the password manager prompt were never bypassed: the
prompt was dismissed, and you unlocked the phone when it locked.

## Found on the phone

Every one passed all the unit and widget tests. Each now has a test that
fails on the old code.

| # | Bug | Fix | Test |
|---|---|---|---|
| 1 | The notification offer on the ticket screen crashed: the phone's preference storage accepts only listed keys, and the new keys were not listed. The device ids used one key per account and ticket, which can never be listed | Listed `dory.notification_offer_dismissed`; device ids moved into one listed key, `dory.push_devices` (JSON) | The in-memory storage now enforces the list (18 tests fail on the old code) |
| 2 | Closing a ticket screen during a refresh threw "Cannot use the Ref after it has been disposed". This race dates from Phase 05; the phone hit it | Read dependencies before the first `await`; skip the guest list update once disposed. Same guard in four notification provider methods | `ticket_details_dispose_test` |
| 3 | **Notifications never reached the open app.** Laravel names the broadcast after its class unless the notification defines `broadcastAs()`; the app listens for `notification.created` | `QueueAlert::broadcastAs()` | `test_the_app_hears_new_notifications_on_the_users_channel` (name, channel, payload) |
| 4 | Back from a ticket opened by a notification left DORY | The target opens on top of the current screen; across the customer and business areas it still replaces | "Back from an opened notification returns to DORY" |
| 5 | **After a cold start with a stored session, the user's channel was never opened**, so no live alerts until another screen connected. Riverpod 3 pauses a provider that is only read, and the coordinator was only read | `NotificationsHost` keeps a listener on the coordinator | "a session restored after launch still gets its private channel" |
| 6 | The bell kept an old count after the background | The badge is read whenever the user channel is confirmed again | "is read again whenever the user channel is confirmed" |

Also changed after the phone test:

- `start_dory.bat` restarts a service that stopped inside its window.
- The staff screens say "Live" or "every 15 seconds", matching what they do.
- Two Arabic strings were reworded: the live note and the "Allow" button.

## Security

- Private channels: authorization reads the database for each request.
  Refused requests get 403, and the same 403 when the record does not
  exist. Tested across businesses, branches, roles, suspended members and
  guests.
- The public guest channel carries the session id, its status and a time.
  It has no names, phone numbers, tokens, ticket ids or counts.
- Client events are off (`accept_client_events_from=none`), and the app
  ignores `client-*` anyway.
- Guest ticket tokens never appear in a URL, channel name, broadcast, push
  payload or log. Guest device registration requires the ticket token in
  `X-Ticket-Token`.
- FCM tokens are encrypted at rest, never returned, and never logged. The
  service account file lives outside the repository and is not in Flutter.
- Notifications, broadcasts and pushes are sent only after the queue change
  committed. A rolled-back change announces nothing.
- `google-services.json`, the service account, `.env`, `.dory/` and
  `scripts/dev/dory.local.psd1` are ignored by Git. No secret was committed
  (checked before each commit).

## Known limitations

1. **Push is off** until Firebase is configured. Closed apps get no alert.
2. **A force-stopped Android app receives no FCM message** until it is opened
   again. That is Android's rule ([PUSH_NOTIFICATIONS.md](PUSH_NOTIFICATIONS.md#the-app)).
3. Reverb does not replay missed events. Screens read the API once after
   reconnecting instead.
4. Without Reverb, the notification badge does not refresh by itself while
   the app sits open. With Reverb it is read on every (re)connection.
5. Guests have no notification center (no account). Their alerts are push
   only, so with push off they see changes only on the open ticket screen.
6. Old notification and device rows are kept. Pruning is not part of this
   phase.
7. `firebase_messaging` stays on 16.7: version 17 replaces the token API,
   and that change needs a real Firebase project to verify
   ([FIREBASE_SETUP.md](FIREBASE_SETUP.md#package-versions)).
8. One Reverb server, no Redis. Scaling out needs Redis
   ([REVERB_SETUP.md](REVERB_SETUP.md#production)).
9. Release builds need `wss` and a real host for Reverb. There is no
   production setup yet.
10. After a long Reverb outage the app can take up to 30 s to be live again
    (the retry delay ceiling); it polls meanwhile.
11. A queue worker exits when its database connection breaks, for example
    after the computer slept. `start_dory.bat` restarts it; in production a
    process manager must.
12. Right after an Android build, the PHP development server can take 10 to
    30 s for its first requests. The app then shows its retry screen. This
    is local only.

## Local changes outside the repository

- `backend/.env` (ignored by Git) gained `BROADCAST_CONNECTION=reverb`, the
  `REVERB_*` keys (random, local), `FCM_ENABLED=false` and the queue
  settings. A backup is in `backend/.env.backup`.
- `dory_db`: **unchanged**. The four Phase 06 migrations are pending: `start_dory.bat` lists them and runs them only if you answer yes. Until then, the notification screens and notification jobs fail on `dory_db`. It still has no users or businesses.
- `dory_db_testing` held the phone-test data. The MariaDB suite afterwards
  reset it, as every run does.
- The local MySQL 8.4 test server is stopped. Nothing listens on 8000 or 8080,
  and `adb reverse` is removed.
- The phone has the debug build that `start_dory.bat` installed, pointing at
  `127.0.0.1` through `adb reverse`, with notifications allowed. Its saved
  session belongs to the reset test database, so it opens signed out.

## Commits

All commits are authored and committed by **Tareq Bassam Bakr
<255685054+Tareq-Bassam-Bakr@users.noreply.github.com>**, with no co-author line.
Each push was verified (`git rev-parse HEAD` = `gh api .../commits/main`). No
force-push, no history rewrite. The repository remains private. No paid
service was activated.

| Commit | Content |
|---|---|
| `6320d72` | API: Reverb, channels, broadcasts, notifications, push devices, FCM sender (C to I) |
| `3042739` | App: Reverb client, Reverb-first tickets and staff screens, FCM handling, notification center (J to N) |
| `242d25d` | Live-mode note on staff screens, push registration follows the app language |
| `d9c1a5c` | `start_dory.bat` and `stop_dory.bat`, documentation, first report (P, Q, S) |
| *(this commit)* | Phone test (O): the six bugs it found, with tests; Phase 06 device tests; restart of stopped services in `start_dory`; report |

## Remaining work

1. Create the Firebase project and test live push on the phone
   ([FIREBASE_SETUP.md](FIREBASE_SETUP.md)).
2. Fix the GitHub Actions account issue, so MySQL 8.4 runs in CI.
3. Production real-time: TLS (`wss`), a process manager, and Redis if more
   than one server is needed.
4. Decide on the four pending migrations on `dory_db` (`start_dory.bat`
   asks).

## Next

Phase 07 has **not** been started and will not be without your explicit
approval.

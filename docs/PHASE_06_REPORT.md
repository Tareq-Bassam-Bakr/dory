# Phase 06 report: real-time engine, notifications and one-click startup

Date: 2026-11-08 · Baseline: tag `phase-05-baseline-for-06` → `f7a8716` ·
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

**Phone:** the TECNO phone was not connected (`adb devices` empty), so the real-device tests and the three startup checks that need a device have **not run yet**. Everything else was tested on this machine, including the real Reverb server ([Android device](#android-device)).

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
| O. Phone | ⏳ not run yet: no phone connected ([Android device](#android-device)) |
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
- **On the phone:** not run yet ([Android device](#android-device)).

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

All on 2026-11-08, on this machine, on the final code.

| Suite | Result |
|---|---|
| Backend, MySQL 8.4.13 (`composer test:mysql`, local) | ✅ **639 passed** (5559 assertions), including all 8 concurrency tests and the full migration rollback |
| Backend, MariaDB 10.4 (`composer test:mariadb`) | ✅ **639 passed** (5559 assertions), including all 8 concurrency tests |
| Backend, SQLite (`composer test`) | ✅ **630 passed, 9 skipped** (5367 assertions). The 9 need MySQL or MariaDB |
| Pint | ✅ passed |
| Flutter (`flutter test`) | ✅ **572 passed** |
| Flutter analyzer | ✅ no issues |
| `dart format` | ✅ no changes |
| On-device tests (`integration_test`) | ⏳ not run: no phone connected |
| GitHub Actions | ⚠️ not run: it still does not start for this repository (account-level) |

Phase 06 added 63 backend tests (576 → 639) and 63 Flutter tests (509 → 572).
No existing test was removed or weakened. Changed existing tests:

- `RouteProtectionTest`: its explicit list of public routes gained the two
  guest device routes.
- `customer_screens_test.dart`: Phase 05's "notifications are an honest
  placeholder" checked that the bell said notifications would come later.
  They exist now, so the test became "the bell opens the notification
  center".
- Test helpers gained optional parameters (notifier, push, overrides).

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
| 5 | Flutter starts on a connected device | ⏳ not run: no device connected. The script found none and said so, and kept the backend running |
| 6 | The app authenticates against the local server | ⏳ needs the phone |
| 7 | Real-time updates work on the device | ⏳ needs the phone. Real-time itself was tested against the real Reverb server ([Real-time results](#real-time-results)) |
| 8 | `stop_dory.bat` stops only DORY processes | ✅ a decoy PHP process and MariaDB (`mysqld` 4616) survived; ports 8000 and 8080 freed; run state removed |
| 9 | XAMPP MariaDB remains unaffected | ✅ same process before and after, never touched |
| 10 | A second start does not duplicate | ✅ "already running" for each service; PHP process count unchanged (4 → 4) |
| extra | A port held by another program | ✅ the start refused, naming the process; nothing started or stopped |
| extra | Pending migrations | ✅ listed with a `[y/N]` question; answering no left `dory_db` unchanged |

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

Not run yet: the TECNO KM7 (Android 15) was not connected (`adb devices`
listed nothing). Planned when it is back, from your list:

| # | Test | Status |
|---|---|---|
| 1 | DORY opens normally | ⏳ |
| 2 | Customer joins a queue | ⏳ |
| 3 | Staff calls the customer | ⏳ |
| 4 | Ticket updates at once through Reverb while visible | ⏳ |
| 5 | Reverb disconnects and reconnects | ⏳ |
| 6 | App goes into the background | ⏳ |
| 7 | FCM notification arrives | ⛔ cannot run: Firebase is not configured |
| 8, 9 | Tapping a notification opens the right ticket | ⏳ with a local notification (Reverb while open); FCM taps need Firebase |
| 10 | Reopened after a process restart | ⏳ |
| 11 | Guest ticket still opens securely | ⏳ |
| 12 | Business dashboard receives queue changes | ⏳ |
| 13 | Arabic notification display | ⏳ |

The on-device tests (`flutter test integration_test`) wait for the phone too.

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
4. Without Reverb, the notification badge refreshes on sign-in, when a push
   arrives in front and on the Notifications screen, not by itself.
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
10. Real-device behaviour of Phase 06 (live ticket on the phone, background and reconnect, Arabic local notifications) is not verified until the phone tests run.

## Local changes outside the repository

- `backend/.env` (ignored by Git) gained `BROADCAST_CONNECTION=reverb`, the
  `REVERB_*` keys (random, local), `FCM_ENABLED=false` and the queue
  settings. A backup is in `backend/.env.backup`.
- `dory_db`: **unchanged**. The four Phase 06 migrations are pending: `start_dory.bat` lists them and runs them only if you answer yes. Until then, the notification screens and notification jobs fail on `dory_db`. It still has no users or businesses.
- `dory_db_testing` was used for the live Reverb check and the test suites.
- The local MySQL 8.4 test server is stopped. Nothing listens on 8000 or 8080.

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
| *(this commit)* | Live-mode note on staff screens, language-aware device registration, startup scripts, documentation, report (O to T) |

## Remaining work

1. Create the Firebase project and test live push on the phone
   ([FIREBASE_SETUP.md](FIREBASE_SETUP.md)).
2. Fix the GitHub Actions account issue, so MySQL 8.4 runs in CI.
3. Production real-time: TLS (`wss`), a process manager, and Redis if more
   than one server is needed.
4. Run the phone tests and the three device checks of the startup test ([Android device](#android-device)).
5. Decide on the four pending migrations on `dory_db`.

## Next

Phase 07 has **not** been started and will not be without your explicit
approval.

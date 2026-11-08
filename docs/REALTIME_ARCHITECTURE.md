# Real-time architecture

Phase 06 replaces routine polling with Laravel Reverb (self-hosted
WebSockets), keeps polling as the fallback, and adds notifications. The
REST API stays the source of truth: a WebSocket event only says *that*
something changed; the app then reads the data from the API.

## From a queue change to the screen

```
staff action (API) ── transaction ──┬─ ticket / session row updated
                                    └─ queue_events row (history)
                                           │  QueueActivity (after commit only)
                                           ▼
                              QueueActivityRelay (in the request)
                 ┌─────────────────────────┼───────────────────────────┐
                 ▼                         ▼                           ▼
   broadcast jobs ("realtime")   ProcessQueueNotifications    (nothing else in
   QueueSignal, TicketUpdated,   job ("notifications")         the request)
   StaffQueueUpdated                       │
                 │               notifications (DB, user channel, push jobs)
                 ▼
        queue worker ──► Reverb ──► app: refresh from the API
```

- **After commit only.** `QueueActivity` implements
  `ShouldDispatchAfterCommit`, the broadcast events too, and the database
  queue has `after_commit => true`. A rolled-back change announces nothing
  (tested in `QueueBroadcastsTest`, `QueueNotificationsTest`).
- **Never in the way.** Broadcasting and notifications are queued. If the
  worker or Reverb is down, the staff action still succeeds; apps fall back
  to polling.
- **Every event comes from a recorded state change** (`queue_events`), never
  from the client.

## Channels

See [BROADCAST_CHANNELS.md](BROADCAST_CHANNELS.md).

| Channel | Who | What |
|---|---|---|
| `queue-updates.{session}` (public) | anyone holding a ticket of the session, guests included | "changed", the session status and the time; nothing personal |
| `private-tickets.{ticket}` | the signed-in owner of the ticket | status and event of that ticket |
| `private-queue-sessions.{session}` | managers who reach the branch, employees assigned to the queue | ticket and session changes, ids and statuses only |
| `private-businesses.{business}` | owner, managers of every branch | the same for every queue of the business |
| `private-users.{user}` | that user | "a notification was created" |

## The app

- `PusherClient` (`lib/core/realtime/pusher_client.dart`): Pusher protocol 7
  over `web_socket_channel`. One connection for the app, opened when
  something subscribes and closed 20 seconds after the last listener leaves.
  Subscriptions are counted, so two screens share one. Private channels are
  signed by `POST /api/v1/broadcasting/auth` with the user's token. It pings
  a silent server and reconnects with delays of 1, 2, 4, 8, 16, then 30
  seconds. It closes in the background and reconnects in front. Events sent
  by clients (`client-*`) are ignored, and Reverb refuses them anyway.
- `LiveTicketSync` (ticket screen) and `AutoRefresh` (staff screens):
  - **Live** (connected and every channel confirmed): no timer. Each event
    triggers one API read, and bursts collapse into one.
  - **Not live:** the 15-second polling of Phase 05.
  - **After reconnecting:** one read, because missed events are not replayed.
  - **Hidden screens:** neither poll nor read.
  - **The screen says which mode it is in:** "Live: changes appear at
    once" or "Updates automatically every 15 seconds" on staff screens, and
    the matching note on the ticket screen.
- One client per account: signing in or out rebuilds it, and every screen
  subscribes again with the new token.

Real-time is off unless the build passes `DORY_REVERB_APP_KEY`; tests and
builds without it behave exactly as in Phase 05.

## Notifications

See [NOTIFICATION_EVENTS.md](NOTIFICATION_EVENTS.md),
[NOTIFICATION_CENTER.md](NOTIFICATION_CENTER.md) and
[PUSH_NOTIFICATIONS.md](PUSH_NOTIFICATIONS.md).

## Scaling later

Locally and on one server: Reverb in single-server mode, the database
queue, no Redis. To run several Reverb servers or workers, configure Redis,
set `REVERB_SCALING_ENABLED=true`, and move `QUEUE_CONNECTION` and
`CACHE_STORE` to `redis` ([REVERB_SETUP.md](REVERB_SETUP.md#production)).

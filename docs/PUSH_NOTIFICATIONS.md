# Push notifications

DORY sends push notifications with Firebase Cloud Messaging (FCM) through the
HTTP v1 API (`kreait/laravel-firebase` 7.2, `firebase_messaging` 16.7).

**Status: implemented, off, and never delivered to a phone.** No Firebase
project exists yet. Every part runs and is tested with push off or with a
fake sender. Turning it on needs a Firebase project
([FIREBASE_SETUP.md](FIREBASE_SETUP.md)) and a test on a real phone.

## When push is off (today)

| Part | Behaviour |
|---|---|
| Backend `FCM_ENABLED=false` | `PushChannel` queues nothing; `DisabledPushSender` is bound |
| Backend `FCM_ENABLED=true` without a readable `FIREBASE_CREDENTIALS` file | still `DisabledPushSender`: a missing secret never breaks a request |
| App without `--dart-define=DORY_FCM_ENABLED=true` | `DisabledPushMessaging`: Firebase is never started, no token, no registration |
| Android without `android/app/google-services.json` | the Google services plugin is not applied; the build works |

Notifications still reach signed-in users through the notification center
and, while the app is open, through Reverb (`notification.created` on
`private-users.{id}`), shown as a local notification. Guests see their ticket
update on screen (Reverb or polling) but get no alert while the app is
closed.

## When push is on

```
QueueAlert ──► PushChannel ──► SendPushMessage job (per device, queue "notifications")
                                  │  device still active? decrypt token
                                  ▼
                          FirebasePushSender ──► FCM HTTP v1 ──► phone
```

- **One job per device**, unique per notification and device
  (`ShouldBeUnique`), so a retried notification job does not queue it twice.
- **Text in the device's language** (`push_devices.locale`, English or Arabic).
- **Outcomes**, mapped from FCM errors (`FirebasePushSenderTest`):

  | Outcome | FCM | What DORY does |
  |---|---|---|
  | accepted | message id | nothing more; FCM accepted it, which is not delivery |
  | invalid token | `NotFound`, `SenderIdMismatch`, invalid registration token | revokes every row with that token |
  | retry | server error, unavailable, quota, connection failure | retries: 10 s, 60 s, 300 s |
  | failed | anything else | logged (device public id and reason, never the token) |

- **Android:** channel `dory_queue_updates`, small icon `ic_stat_dory`, and a
  tag per ticket, so a newer alert about a ticket replaces the older one. "Called"
  and "almost your turn" are sent with high priority; everything else with
  normal priority.
- **iOS:** an APNs block is built, but iOS is not configured
  ([FIREBASE_SETUP.md](FIREBASE_SETUP.md#j-ios-later)).

## Payload

```json
{
  "notification": {"title": "It's your turn", "body": "Cafe Dory, ticket A-012 is called. Please go to the counter."},
  "data": {"kind": "ticket_called", "ticket": "01J…", "queue": "01J…", "guest": "0", "notification": "9d3c…"}
}
```

The payload holds the kind, public ids, a guest flag, and the notification
id (signed-in users only, so a tap can mark it read). It carries no
guest ticket token, phone number, customer name or internal id. Business
name and ticket number appear only in the visible text, as on the lock
screen.

## The app

- **Permission is asked only when it helps**, never at start:
  - on the ticket screen after joining ("Get notified when it's your turn"),
    which can be dismissed for good;
  - in Notification settings.

  Android 13+ shows the system dialog. A refusal is respected; settings then
  offer "Open phone settings" (`ACTION_APP_NOTIFICATION_SETTINGS`).
- **Registration** ([DEVICE_TOKEN_SECURITY.md](DEVICE_TOKEN_SECURITY.md)):
  - signed-in users: after sign-in and after the permission is granted;
    removed before sign-out;
  - guests: per active ticket, with the ticket token;
  - a new token replaces the old registrations.
- **Receiving:**

  | App state | What happens |
  |---|---|
  | In front | Android does not display FCM itself. DORY shows a local notification, unless the same ticket is on screen; one alert even if Reverb announced it too |
  | Background | Android displays it from the notification part |
  | Swiped away (not running) | Android displays it the same way |
  | **Force-stopped** (Settings → Force stop) | **nothing arrives** until the user opens DORY again. Android's rule; no app can change it |

- **Tapping** opens the target screen: the ticket (signed in or guest), the
  staff queue page for staff alerts, or the notification center. Targets
  are checked: unknown kinds and malformed ids open the notification center.
  A tap that starts the app waits until sign-in is restored. If the user is
  signed out, it opens sign-in first and then the target.

## Not sent

- Position changes. The ticket screen updates live instead. The exception is
  "almost your turn", sent once per ticket.
- What customers did themselves (their own cancellation).
- Alerts to staff about their own actions.
- Disabled preferences ([NOTIFICATION_CENTER.md](NOTIFICATION_CENTER.md)), or
  push when "push notifications" is off (the notification center still gets
  it).

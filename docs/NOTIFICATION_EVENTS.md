# Notification events

Which queue changes notify whom. Rules: `QueueNotificationPlanner`.
Texts: `backend/lang/{en,ar}/notifications.php`. Tests:
`tests/Feature/Notifications/QueueNotificationsTest.php`.

Every notification starts from a recorded queue change (`queue_events`),
after it was committed, in a queued job. The client never asks for one.

## Customers (the ticket holder: signed-in user or guest)

| Change | Kind | Preference | Priority |
|---|---|---|---|
| Called | `ticket_called`: "It's your turn" | `ticket_updates` | high |
| Put back to waiting (recall) | **nothing**: the ticket screen updates; the next call notifies again | | |
| Service started | `ticket_service_started` | `ticket_updates` | normal |
| Completed | `ticket_completed` | `ticket_updates` | normal |
| Skipped | `ticket_skipped` | `ticket_updates` | normal |
| No-show | `ticket_no_show` | `ticket_updates` | normal |
| Cancelled by the business | `ticket_cancelled` | `ticket_updates` | normal |
| Cancelled by the customer | **nothing**: they did it themselves | | |
| Session closed or ticket expired | `queue_closed` | `queue_updates` | normal |
| Session paused | `queue_paused`, to every active ticket | `queue_updates` | normal |
| Moved within N places of the counter | `ticket_almost_turn`: "Almost your turn" / "you are next" | `almost_turn` | high |

**Almost your turn:** after a call, skip or cancellation of a waiting ticket,
while the session is open, the first N + 1 waiting tickets in serving order
(priority, then join order) are told how many people are ahead. N comes
from `NOTIFY_ALMOST_TURN_PEOPLE_AHEAD` (default 2). Each ticket is told
**once**: the key is per ticket, not per event. Other position changes
notify nobody; the ticket screen updates live.

## Staff

| Change | Kind | Preference | Who |
|---|---|---|---|
| A customer joined | `staff_ticket_joined` | `staff_new_tickets` | every member who can operate the queue (owner, managers of the branch, assigned employees) |
| A customer cancelled | `staff_ticket_cancelled` | `staff_cancellations` | same |

Nobody is told about their own action. Suspended or inactive members get
nothing.

## Once, and only after commit

- Every alert has a key: `event:{id}:holder`, `event:{id}:user:{id}`,
  `event:{id}:ticket:{id}`, or `ticket:{id}:almost_turn`.
  `NotificationDispatcher` writes the key to `notification_dispatches` and
  stores the notification in one transaction. A retried job or a second
  worker finds the key and sends nothing. A failure before the commit
  leaves no key, so the retry sends it.
- Guests: the key is claimed, then push goes to the ticket's registered
  devices. With push off, the key is claimed and nothing is sent.
- A rolled-back queue change announces nothing.

## Language

- Push: the language of the device registration (`en`/`ar`).
- Notification center: `Accept-Language`, else the account language.

Placeholders: business name, queue name, ticket number, people ahead. Never
a customer name or phone number.

## Delivery is not claimed

A stored notification is in the center. A push job reports what FCM
answered. "Accepted" means FCM took the message, not that the phone showed
it ([PUSH_NOTIFICATIONS.md](PUSH_NOTIFICATIONS.md)).

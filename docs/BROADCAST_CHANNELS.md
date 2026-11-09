# Broadcast channels

All rules are in `backend/routes/channels.php`. They are checked through
`POST /api/v1/broadcasting/auth`, with the user's Sanctum token and an
active account. Every rule reads the database: the channel name the app
asks for is never trusted on its own. Tests:
`tests/Feature/Realtime/BroadcastChannelsTest.php`, through the real
endpoint and checking the Reverb signature.

## Private channels

| Channel | Allowed | Refused |
|---|---|---|
| `private-users.{userPublicId}` | that user | anyone else |
| `private-tickets.{ticketPublicId}` | the signed-in user who owns the ticket | other users, the business, guest tickets (no account) |
| `private-queue-sessions.{sessionPublicId}` | the owner; managers who reach the branch; employees **assigned to this queue** | employees not assigned, managers of other branches, other businesses, suspended memberships |
| `private-businesses.{businessPublicId}` | the owner; managers with access to **all** branches | branch managers (they would see other branches), employees, other businesses |

A refused channel answers 403. A missing record gives the same answer.

## The public guest channel

`queue-updates.{sessionPublicId}`: guests have no account, so they cannot be
authorized for private channels. They listen here and then read their own
ticket through the ticket token API (`X-Ticket-Token`), which is unchanged
since Phase 04.

Anyone who knows the session id can listen. The payload is limited
accordingly:

```json
{"session": "01J…", "session_status": "open", "changed_at": "2026-11-08T09:23:18+00:00"}
```

It has no ticket ids, numbers, names, phone numbers or tokens, and no
counts. The session id reaches a guest only inside their own ticket, which
needs the ticket token.

## Events

| Event (`broadcastAs`) | Channels | Payload |
|---|---|---|
| `queue.changed` | `queue-updates.{session}` | `session`, `session_status`, `changed_at` |
| `ticket.updated` | `private-tickets.{ticket}` | `ticket`, `status`, `event`, `occurred_at` |
| `queue.updated` | `private-queue-sessions.{session}`, `private-businesses.{business}` | `kind`, `queue`, `session`, `ticket`, `status`, `occurred_at` |
| `notification.created` | `private-users.{user}` | `kind`, `ticket`, `id`, `type` (`notification.created`) |

`kind` of `queue.updated` is one of: `ticket_joined`,
`ticket_status_changed`, `customer_cancelled`, `session_changed`,
`queue_status_changed`, `capacity_changed`, `settings_changed`.

No event carries a name, phone number, email or token. Payload keys are
fixed by tests (`QueueBroadcastsTest`, and
`QueueNotificationsTest::test_the_app_hears_new_notifications_on_the_users_channel`).

`notification.created` is the event name because `QueueAlert` defines
`broadcastAs()`. Without it, Laravel names the event after its class
(`Illuminate\Notifications\Events\BroadcastNotificationCreated`), and the
app never hears it. The phone test found this; the unit tests on both sides
had assumed the name. Client events (`client-*`) are
refused by Reverb and ignored by the app.

# Notification center

Signed-in users keep their notifications in DORY, whether or not push is
on. Guests have no account and therefore no center: their alerts are push
only, to the phone that holds the ticket.

## Storage

Laravel's `notifications` table (`QueueAlert`, `database` channel):

- `type` is the kind (`ticket_called`, …);
- `data` holds the kind, public ids, ticket number, queue and business name,
  and facts such as `people_ahead`;
- `read_at`.

The data holds no titles and no texts. They are rendered when read, in
the reader's language, so switching to Arabic also changes old entries.

## API (signed in, own notifications only)

| Endpoint | |
|---|---|
| `GET /api/v1/me/notifications?filter=all\|unread\|read&page=n` | 20 per page, newest first. `meta.pagination`, `meta.unread_count` |
| `POST /api/v1/me/notifications/{id}/read` | marks one as read; another user's id gives 404 |
| `POST /api/v1/me/notifications/read-all` | `{marked: n}` |
| `GET /api/v1/me/notification-preferences` | the flags below |
| `PUT /api/v1/me/notification-preferences` | any subset of the flags (booleans) |

Each entry: `id`, `kind`, `title`, `body` (language from
`Accept-Language`, else the account), `ticket {id, number}`,
`business {id, name}`, `queue {id, name}`, `for_staff`, `read`, `read_at`,
`created_at`.

## Preferences

| Flag | Covers | Default |
|---|---|---|
| `ticket_updates` | called, service started, completed, skipped, no-show, cancelled by the business | on |
| `almost_turn` | almost your turn | on |
| `queue_updates` | queue paused, queue closed | on |
| `staff_new_tickets` | a customer joined (staff) | on |
| `staff_cancellations` | a customer cancelled (staff) | on |
| `push_enabled` | push to this account's phones; the center still gets everything else | on |

A switched-off kind is not stored either, so it never appears later. The
phone's own permission is separate: it is shown and requested on the same
screen.

## The app

- **Bell** with an unread badge on the customer home and the business
  dashboard. It opens Notifications.
- **Notifications** (`/customer/notifications`), for staff too:
  - filters All, Unread and Read;
  - endless scroll and pull to refresh;
  - "Mark all as read";
  - tapping an entry marks it read and opens its ticket, or the queue for
    staff alerts.
- **Notification settings** (from the Notifications screen):
  - the phone permission: allow, or open the phone settings when it was
    refused;
  - the six switches. Everyone sees all six; the two staff switches only
    matter to staff.

  Changes save at once and switch back with an error if saving fails.
- **Live:** while the app is open and signed in, `notification.created` on
  `private-users.{id}` refreshes the badge and shows a local notification,
  unless that ticket is already on screen.
- The badge is also read whenever the user's channel is confirmed: at
  start and sign-in, after returning from the background, and after a
  reconnection, because alerts sent meanwhile are not replayed. It is also
  read when a push arrives in front and when the Notifications screen
  loads. Without Reverb it does not refresh by itself while the app sits
  open.

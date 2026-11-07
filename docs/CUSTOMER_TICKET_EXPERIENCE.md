# Customer ticket experience

Joining and following a ticket from the app. Tickets are created by the Phase
04 engine ([QUEUE_ENGINE_ARCHITECTURE.md](QUEUE_ENGINE_ARCHITECTURE.md)); this
document covers what the customer sees.

## Joining

`QueueJoinPage` reads the queue from the server when it opens, then offers:

| Who | What happens |
|---|---|
| Signed in | `POST /queues/{id}/join`. The ticket belongs to the account |
| Visitor, guests allowed | A form with **only** the fields the queue requires, then `POST /public/queues/{id}/join` |
| Visitor, guests not allowed | An invitation to sign in |

A phone number, when required, needs the guest's consent, as in Phase 04.

**No double tickets.** One idempotency key lives as long as the screen, so a
retry after a lost response returns the same ticket. A synchronous guard also
stops a second tap in the same frame, before the button has rebuilt as
disabled.

After joining, a message confirms the ticket number and the ticket opens.

## The digital ticket

`CustomerTicketPage` shows the ticket number, a headline for its state,
position, people ahead, estimated wait, business, branch, address, service,
join time and session. It shows ticket numbers being served, never people.

| State | Headline | Actions |
|---|---|---|
| `waiting` | You are in the queue | Leave the queue |
| `called` | It is your turn | Shows when to arrive by |
| `serving` | You are being served | None |
| `completed` | Service completed | Rate it, if signed in |
| `cancelled`, `skipped`, `no_show`, `expired` | Says what happened | None |

Finished tickets have no ordinary actions. Cancelling asks first; the server
allows it only while the ticket is waiting.

Instructions from the business are not shown: the backend has no such field
yet.

## Position updates: polling, not real-time

**There is no real-time push in this version.** An open ticket asks the server
for its state on a timer, and the screen says updates are not instant.
WebSockets and push notifications belong to a later phase.

`TicketPoller` follows these rules:

| Rule | Behaviour |
|---|---|
| Interval | 15 seconds (`--dart-define=DORY_TICKET_POLL_SECONDS=...`) |
| Only while on screen | Stops in the background, under another screen, and in a hidden tab |
| Coming back | Refreshes at once |
| Failures | Waits 2×, 4×, 8×... up to 2 minutes, and keeps the last ticket on screen |
| One at a time | The next request is scheduled only after the previous one ends |
| Finished ticket | Stops for good |
| One ticket only | Lists never poll; they refresh on opening and on pull |

These limits are the reason a customer can miss being called if they are not
looking at the screen. That is a known limitation until notifications exist.

## Ticket center

`TicketsPage` groups tickets by Active, Waiting, Called, Serving, Completed,
Cancelled and History. Each maps to a server filter on `GET /me/tickets`, and
history loads page by page. See
[GUEST_TICKET_PERSISTENCE.md](GUEST_TICKET_PERSISTENCE.md) for guest tickets.

## Ratings

Signed-in customers can rate a **completed** ticket from 1 to 5, once.

- One rating per ticket, enforced by a unique index.
- Only the ticket's holder; another customer gets a 404.
- Not guests: only an account gives a verified visit.
- Not the business's own team.
- Ratings only, without text, so nothing needs moderation.
- A business's rating is the real average. It stays hidden until
  `DISCOVERY_MIN_RATINGS` ratings exist. No rating is ever generated.

`TicketReviewService` writes the rating and the summary on `businesses` in one
transaction, under a lock on the business row. A rating cannot be changed in
this version.

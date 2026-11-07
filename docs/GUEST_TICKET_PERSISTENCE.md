# Guest ticket persistence

A guest has no account, so the ticket's access token is their only proof. It
must survive restarts and must never leak.

## The contract, unchanged from Phase 04

1. `POST /public/queues/{id}/join` returns `access_token`, once.
2. The server stores only its SHA-256 hash.
3. Every later request sends it in the `X-Ticket-Token` header, never in a URL.
4. A wrong or missing token is the same 404 as an unknown ticket.

The only addition is that a guest can join by queue id, because the app finds
queues through discovery and never sees the link token. Same service, same
token, same rules. See [GUEST_JOINING.md](GUEST_JOINING.md).

## Two stores, on purpose

`GuestTicketVault` (`lib/features/tickets/data/guest_ticket_vault.dart`):

| Store | Holds | Why |
|---|---|---|
| `SecureStore`, key `dory.guest_ticket_token.<id>` | The token, one entry per ticket | Android Keystore, iOS Keychain |
| `KeyValueStorage`, key `dory.guest_tickets` | Ticket id, number, status, business, branch, service, join time | So a ticket can still be listed when its token cannot be read |

The list holds no token, name or phone number. The token is never in
SharedPreferences, in `CustomerTicket`, or in any state a screen renders. It
exists only in `GuestJoinResult`, whose `toString` leaves it out, and it goes
straight from the response into secure storage.

## Behaviour

| Situation | Result |
|---|---|
| App restarted | Tickets are listed from storage; opening one reads it with the stored token |
| Several guest tickets | All are kept, newest first |
| Retried join | The server rotates the token; the stored one is replaced |
| Token missing or unreadable | The ticket stays listed; opening it explains the problem and offers to remove it |
| Server no longer accepts the token | The same explanation |
| Finished ticket | Can be removed from the device; this removes its token too |
| Corrupted list | Treated as empty, never a crash |
| Ticket list opens | Active guest tickets are read once, one by one. They are not polled |

Secure storage can be reset by the system, for example after restoring a
device. The customer's place in the queue is not affected by that; the screen
says so and suggests asking the business.

## Not linked to accounts

**A guest ticket is not linked to an account that is created or signed in to
later.** The backend has no such feature, and no screen suggests otherwise:
signed-in customers see guest tickets in a separate "On this device" section
that says they are not linked. Linking would need a dedicated, tested flow and
is a possible future capability.

## Credentials stay with the API

The token is sent only to the configured API. The map, images and external
links never receive it.

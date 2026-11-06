# Guest joining (no account)

Each queue has a **public link**, `APP_URL/join/{token}` (configurable with
`QUEUE_PUBLIC_JOIN_URL`), and its QR code encodes exactly that URL. Managers
see it in the app (**QR code & link**) or via `GET /queues/{queue}/public-link`,
and can replace it (`POST /queues/{queue}/rotate-public-token`): the old link
and printed QR codes stop working at once.

The token is 40 random characters. The database stores its SHA-256 hash (for
lookups) and an APP_KEY-encrypted copy (so the link can be shown again). It
identifies the queue only; it grants nothing else.

## Two ways in

1. **Web page** (any phone camera, no app): `/join/{token}` served by the
   backend, English and Arabic (RTL), chosen by `?lang=`, a remembered cookie,
   or `Accept-Language`.
2. **API** (for a future web or app client): `GET /api/v1/public/queues/{token}`
   and `POST /api/v1/public/queues/{token}/join`.

Signed-in customers join with `POST /api/v1/queues/{queue}/join` instead
(their ticket is tied to their account).

## Minimum information

| Field | Rule |
|---|---|
| name | required when the queue asks for it (default), 2–80 characters |
| phone | optional unless the queue requires it; E.164 (`+963944123456`) |
| consent | required **only when a phone number is given**: the guest accepts that the business may contact them about this ticket |

The phone number is stored **encrypted** and is shown only to staff who
operate the queue (masked, e.g. `+963••••••456`, to everyone else in the
team). Public endpoints never return names or phone numbers; event metadata
never contains them.

## The ticket access token

Joining returns a 48-character **access token, once**. The database keeps only
its SHA-256 hash.

- **API clients** send it in the `X-Ticket-Token` header:
  `GET /api/v1/public/tickets/{id}` and `POST /api/v1/public/tickets/{id}/cancel`.
  A missing or wrong token is the same 404 as an unknown ticket. A token in
  the query string is ignored.
- **Web page**: the token is kept in a cookie per ticket,
  `dory_ticket_{ticket id}`: encrypted (APP_KEY), `HttpOnly`,
  `SameSite=Strict`, `Secure` over HTTPS, lifetime
  `QUEUE_GUEST_COOKIE_DAYS` (2). The browser is redirected to
  `/ticket/{id}`; **the token never appears in a URL**, so it does not reach
  access logs, history or referrers.

The ticket page shows the number, status, position, people ahead, an estimate
(labelled as an estimate), the numbers being served, an "arrive before" time
when called, and **Leave the queue** while waiting. It refreshes itself every
20 seconds (no real-time push yet).

## Duplicates and retries

- A browser that already holds an active ticket in the queue is sent back to
  it instead of creating a second one.
- `Idempotency-Key` (16–64 URL-safe characters) makes a join safe to retry: the
  same key returns the same ticket. Guests then receive a **fresh** token
  (the first response, with the original one, was presumably lost) and the
  previous token stops working. The web form sends a random key automatically.
- Guests have no identity, so "one active ticket per person" is enforced only
  for signed-in customers. Guest joins are rate-limited per IP and queue.

## When joining is refused

`guest_join_disabled` (the queue only accepts signed-in customers),
`queue_inactive`, `queue_closed`, `queue_paused`, `queue_full`. The web page
explains each in the guest's language.

# Queue engine security

## Authorization

| Ability | Who | Checked by |
|---|---|---|
| view a queue, its sessions, dashboard, tickets | members who reach the branch | `QueuePolicy::view` |
| manage (configure, delete, staff, public link, call out of order) | owner; manager with access to the branch | `QueuePolicy::manage`, `BranchPolicy::manageQueues` |
| operate (open/pause/resume/close, call next, serve) | managers as above, plus employees **assigned** to the queue | `QueuePolicy::operate` |
| start / complete / no-show / recall a called ticket | the staff member who called it, or a manager | `QueueOperationsService` |
| follow / cancel own ticket | the customer (`{myTicket}` resolves only the user's tickets) or the guest token holder | `PublicQueueResolver`, `EnsureTicketAccessToken` |

- Staff route parameters (`{queue}`, `{queueSession}`, `{ticket}`,
  `{assignment}`) are resolved **through the user's memberships and branch
  access** (`TenantResolver`). Other tenants and other branches get the same
  404 as a missing record (`queue_not_found`, `session_not_found`,
  `ticket_not_found`).
- Suspended businesses can be read but not changed (`403 business_suspended`).
- Flutter hides what a user cannot do, but **the API re-checks every action**.
- `branch_id`, `service_id`, `business_id`, `user_id`, `status` (of tickets),
  `priority`, tokens and ids are never accepted from clients (`prohibited`).

## Secrets

| Secret | Stored as | Travels in |
|---|---|---|
| Public link token (40 chars) | SHA-256 hash + APP_KEY-encrypted copy | the join URL / QR code (it only identifies the queue) |
| Guest access token (48 chars) | SHA-256 hash only | response body once; then `X-Ticket-Token` header, or an encrypted HttpOnly SameSite=Strict cookie on the web page. **Never in a URL.** |
| Idempotency keys | SHA-256 of scope + key | `Idempotency-Key` header |
| Guest phone numbers | encrypted (APP_KEY) | staff ticket details only |

Token comparisons use `hash_equals`. Stack traces never contain argument values
(`zend.exception_ignore_args` is forced in `bootstrap/app.php`).

## Privacy

- Public endpoints and pages return counts, ticket numbers and rules, never
  other customers' names or phones.
- Staff lists mask phone numbers; full numbers appear only on ticket details,
  and only for staff who operate the queue.
- Event metadata never stores names, phones or tokens; guest actions are
  recorded as `{"actor": "guest"}`.
- Ticket `public_id`s are random (not time-ordered ULIDs), so they reveal
  neither the order nor the time of joining.

## Web page protections

The backend keeps no sessions (`SESSION_DRIVER=array`), so Laravel's
session-based forgery checks (`PreventRequestForgery` in Laravel 13's web
group, and `ValidateCsrfToken`) are excluded from the guest routes; a test
guards that exclusion, because the test suite itself bypasses those checks.
The guest forms are protected instead by:

1. `VerifyGuestFormSubmission`: `Sec-Fetch-Site`, when sent, must be
   `same-origin`/`none`, and `Origin`, when sent, must be this site,
   otherwise `419`. `Origin: null` is ignored: browsers send it on form posts
   from pages with `Referrer-Policy: no-referrer`, as ours are.
2. A **double-submit nonce** on the join form: a random value in a
   `SameSite=Strict`, HttpOnly cookie that must equal the hidden form field.
3. The **SameSite=Strict ticket cookie**: other sites cannot make the browser
   send it, so they cannot cancel a guest's ticket.

Pages are `Cache-Control: no-store, private`, `noindex`, `Referrer-Policy:
no-referrer`, `X-Frame-Options: DENY`.

## Rate limits (per minute, `.env`)

| Limiter | Default | Key |
|---|---|---|
| `queue-join` | 10 (`RATE_LIMIT_QUEUE_JOIN_PER_MINUTE`) | user or IP **and** queue; plus 20 per IP across queues |
| `queue-public` | 60 (`RATE_LIMIT_QUEUE_PUBLIC_PER_MINUTE`) | IP (public API and web pages) |
| `queue-operations` | 120 (`RATE_LIMIT_QUEUE_OPERATIONS_PER_MINUTE`) | user (open/pause/close, call, serve) |
| `api` | 60 | every API route |

## Tests that guard this

`QueueConfigurationTest`, `QueueSessionTest`, `QueueOperationsTest`,
`QueueStaffTest` (cross-tenant 404s, roles, suspended businesses),
`GuestJoinTest` (token never stored or echoed, header-only access, encrypted
phone, rate limit), `GuestQueuePageTest` (cookie flags, cross-site rejection,
nonce), `RouteProtectionTest` (every unauthenticated route is on an explicit
list; guest ticket routes must use `ticket.token`).

# Production readiness

A review of what DORY needs before it runs for real customers, made in
Phase 09 by reading the configuration and code. **Nothing was deployed, and
no server or paid service was set up.** Each section says what exists, what
is missing, and what to do. The short list is at the end.

## 1. Environment configuration

**Exists.** All settings come from `backend/.env` (template:
`backend/.env.example`, no secrets in it). Defaults are safe for
development.

**For production, set:**

| Key | Value |
|---|---|
| `APP_ENV` | `production` (also turns on the daily clean-up of expired tokens and reset links, and the breached-password check) |
| `APP_DEBUG` | `false`. With `true`, error pages show internal details. The admin dashboard shows it in red when it is on in production |
| `APP_URL` | The public `https://` address. Payment links, the admin session cookie's Secure flag and links in emails are built from it |
| `APP_KEY` | Generated once on the server (`php artisan key:generate`), never copied from a development machine. It encrypts cookies and stored queue tokens: changing it later breaks existing QR links and sessions |
| `DB_*` | A dedicated database user with rights on DORY's database only |
| `CORS_ALLOWED_ORIGINS` | The web origins that may call the API, not `*` |
| `REVERB_ALLOWED_ORIGINS` | The app's origins, not `*` (section 6) |
| `SANCTUM_TOKEN_EXPIRATION` | Minutes an app sign-in lasts (43200 = 30 days by default) |
| `BILLING_ONLINE_PAYMENTS` | Stays `false` until docs/PAYMENT_GATEWAY_CHECKLIST.md is signed off |
| `ADMIN_SESSION_SECURE_COOKIE` | Leave empty: it follows `APP_URL` (`https` → Secure) |

Then cache the configuration and routes on each deployment:
`php artisan config:cache`, `php artisan route:cache`,
`php artisan view:cache`. After changing `.env`, run `config:cache` again.

**Behind a reverse proxy or load balancer**, configure Laravel's trusted
proxies (`bootstrap/app.php`, `->withMiddleware(...)->trustProxies(...)`)
with the proxy's addresses. Without it, every visitor appears to come from
the proxy: per-address rate limits would count everyone together, the audit
log would record the proxy's address, and HTTPS would not be detected. **Not
configured yet**, because it depends on the hosting.

## 2. Secret management

**Secrets DORY uses:** `APP_KEY`, database password, Reverb app secret, mail
password, Firebase service account file (`FIREBASE_CREDENTIALS`), and later
the payment gateway's API password and notification secret.

**Exists.**

- `.env` and the Firebase credentials folder are ignored by git; only empty
  placeholders are committed.
- Secrets are never logged: the payment client logs status codes and error
  causes only (tested); tokens are stored hashed (Sanctum, password resets,
  queue tokens); the admin dashboard shows only whether provider credentials
  are set.
- The admin dashboard cannot read or change any secret.

**To do.**

- Keep production secrets in one place the team controls (a password
  manager or the hosting provider's secret store), and put them in the
  server's `.env` from there. File permissions: readable by the web server
  user only.
- Give each environment its own secrets (development, test, production).
- Rotate on staff changes and after any suspected leak: database password,
  Reverb secret, gateway password. Rotating `APP_KEY` signs everyone out and
  breaks encrypted values, so plan it.

## 3. Database backups and recovery

**Exists.** Nothing automatic. In Phase 08 a manual `mysqldump` backup was
taken before migrating `dory_db`, and restored copies were checked by hand.

**To do before launch.**

- **Daily full backup** with `mysqldump --single-transaction --routines
  --triggers` (consistent without locking InnoDB tables), or the hosting
  provider's managed backups. Plus binary logs if you need recovery to a
  point in time (lose minutes, not a day).
- **Keep copies off the database server** (another region or provider),
  encrypted, for at least 30 days.
- **Test a restore** into an empty database every month, and before every
  migration in production: a backup that was never restored is not known to
  work.
- **Before every deployment with migrations:** a backup, then
  `php artisan migrate --force`. DORY's migrations have working rollbacks,
  but a backup is the real recovery path.
- Also back up `storage/app/public` (business logos and covers).

## 4. Scheduled billing tasks

**Exists** (`routes/console.php`):

| Task | When | Note |
|---|---|---|
| `billing:sweep` | every 15 minutes, never overlapping | Stores trial ends, unpaid periods and grace ends, and settles expired payment links. Plans in force do not depend on it (they are computed at each request); the records and history do |
| `sanctum:prune-expired --hours=24` | daily, production only | Deletes app sign-ins that expired more than a day ago |
| `auth:clear-resets` | daily, production only | Deletes expired password reset tokens |

**To do.** One cron entry on one server:

```
* * * * * cd /path/to/backend && php artisan schedule:run >> /dev/null 2>&1
```

With several servers, run it on one only (or use `->onOneServer()` with a
shared cache). Watch it: if `billing:sweep` stops, nobody's access changes,
but trial-end and expiry records and the overview's stored counts lag.

## 5. Queue worker monitoring

**Exists.** `QUEUE_CONNECTION=database`: notifications, broadcasts and push
messages are queued in the `jobs` table, failures go to `failed_jobs`. Jobs
are dispatched after the database transaction commits.

**To do.**

- Run workers under a process supervisor (systemd or Supervisor) so they
  restart after a crash or deployment: `php artisan queue:work --tries=3
  --max-time=3600`, and `php artisan queue:restart` on every deployment.
- Watch: the number of waiting jobs (if it grows, customers get late
  notifications), the age of the oldest job, and new rows in `failed_jobs`
  (`php artisan queue:failed`, `queue:retry`).
- The database queue is fine for a pilot. With more traffic, Redis is the
  usual next step (not available on the development machine).

## 6. Reverb (real-time updates) in production

**Exists.** Laravel Reverb serves the live queue screens and ticket updates
(docs/REVERB_SETUP.md). Clients cannot send events
(`accept_client_events_from: none`); private channels are authorized by the
API.

**To do.**

- Run `php artisan reverb:start` under the process supervisor, restarted on
  deployment (`php artisan reverb:restart`).
- Put it behind the same TLS proxy as the API, so apps connect with
  `wss://` on port 443: `REVERB_HOST=<public host>`, `REVERB_PORT=443`,
  `REVERB_SCHEME=https`; Reverb itself listens on
  `REVERB_SERVER_HOST`/`REVERB_SERVER_PORT` (8080) inside. The proxy must
  pass WebSocket upgrades (`Upgrade` and `Connection` headers) and allow long
  connections.
- Set `REVERB_ALLOWED_ORIGINS` to the app's origins (the default `*`
  accepts any website).
- Raise the server's open-file limit (each connection is a file descriptor)
  and set `REVERB_APP_MAX_CONNECTIONS` if needed.
- More than one Reverb server needs `REVERB_SCALING_ENABLED=true` and Redis.

## 7. HTTPS and WebSocket TLS

**Exists.** The API sends conservative security headers; HSTS is left to the
TLS proxy (`SetSecurityHeaders`). The admin dashboard adds a strict CSP and
`no-store`; its cookie becomes Secure on HTTPS. Payment pages refuse
non-HTTPS gateway addresses.

**To do.**

- TLS certificates for the API host (and the Reverb host if separate), with
  automatic renewal (for example Let's Encrypt).
- At the proxy: redirect HTTP to HTTPS, TLS 1.2 or newer, and
  `Strict-Transport-Security: max-age=31536000; includeSubDomains` once
  HTTPS works everywhere.
- `wss://` for Reverb as in section 6. The apps must point to the HTTPS and
  WSS addresses in their release builds.
- Consider restricting `/admin` by network as well (VPN or office addresses
  at the proxy). It is already protected by sign-in, but fewer people can
  even reach it.

## 8. Logging and error monitoring

**Exists.** Laravel logs to `storage/logs/laravel.log` (`LOG_CHANNEL=stack`,
`LOG_STACK=single`). Every API log line carries the request id
(`X-Request-Id`). Expected client errors are not logged as server errors.
The platform audit log (`platform_audit_logs`) and each business's audit log
are kept in the database.

**Missing.** No error monitoring service, no alerting, no log rotation.

**To do.**

- `LOG_STACK=daily` with `LOG_DAILY_DAYS` (for example 14), or ship logs to
  the hosting provider's log service. `LOG_LEVEL=info` or `warning`.
- An error monitoring service (for example Sentry, which has a free tier)
  for exceptions, with alerts by email. Not set up: it is an outside
  service.
- Uptime checks on `/up` and `/api/v1/health`.
- Alerts for: the health check failing, the queue backlog, failed jobs, the
  scheduler not running, and repeated failed admin sign-ins (visible in the
  audit log).

## 9. Rate limiting

**Exists**, per minute (`config/dory.php`, `config/admin.php`, all
adjustable in `.env`):

| Limiter | Limit | Per |
|---|---|---|
| API (signed in) | 60 | user |
| Sign-in and registration | 5, and 20 | email and address; address |
| Password reset | 3 | email and address |
| Invitations | 10 / invitation links 30 | user / address |
| Joining a queue | 10 | user or address, and queue |
| Public queue and ticket pages | 60 | address |
| Staff queue actions | 120 | user |
| Discovery and search | 120 | address |
| Push device registration | 20 | user or address |
| Starting and checking payments | 10 | user |
| Payment webhooks | 120 | address |
| Payment pages | 30 | address |
| Admin dashboard | 120 | admin |
| Admin sign-in | 5, and 20 | email and address; address |

**To do.** Rate limits count in the cache: with several servers, use a
shared cache (database or Redis), not a local file. Behind a proxy they
need the trusted proxies of section 1, or every visitor shares one count.
A network-level limit at the proxy (or the hosting provider's DDoS
protection) is still needed for floods.

## 10. Data retention and privacy

**Personal data DORY stores:**

- accounts: name, email, phone, language, time zone, last sign-in;
- guests' names (and optional phone) on queue tickets;
- push device tokens;
- IP addresses and browsers in the platform audit log and admin sessions;
- business contact details;
- payment records (no card data: cards are entered on the bank's page only).

**Kept today:**

- expired app sign-ins and reset tokens: deleted daily in production;
- everything else, including tickets, queue events, audit logs, payment
  attempts and webhook events: kept without a limit;
- admin sessions: until they expire. Old rows are cleaned by Laravel's
  session lottery.

**Missing, and needed for launch:**

1. **Account deletion.** There is no way for a person to delete their
   account. The App Store and Google Play require apps with sign-up to offer
   it in the app (and Google Play also on a web page). Design needed: what to
   delete and what to anonymize (tickets and payment records that businesses
   and accounting still need).
2. **A privacy policy** that names the data above, why it is kept and for
   how long, and a contact for requests.
3. **Retention periods**, for example:
   - guest names on tickets: anonymized 90 days after the ticket ends;
   - queue events: 2 years for statistics;
   - platform audit log and payment records: as long as the accounting rules
     of the seller's country require (often 5 to 10 years);
   - webhook bodies: 1 year;
   - failed admin sign-in entries: 1 year.

   Then scheduled clean-up commands like the existing ones. Not built: the
   periods are a legal and business decision.
4. Where the servers are, and whether the law of a customer's country
   restricts moving their data there.

## 11. Payment and invoice legal requirements

**Exists.** Every paid billing record has a unique number
(`DORY-2026-000042`) that is never reused, and no billing record is ever
deleted. Each shows what was paid, for which period, how, and when. Paid ones
can be printed from the admin dashboard, marked as either verified online or
received outside DORY and approved by an administrator. Online card payment
is off, and card data never reaches DORY.

**The numbers are not continuous.** A number is the record's database id,
so numbers increase but skip the ids of payment records that were rejected,
and failed online attempts also get a number. Where the law requires one
continuous sequence of invoice numbers per year, a dedicated invoice
sequence, given only to paid invoices, is needed. Not built: it changes how
invoices are numbered, so it waits for your decision.

**Not yet a tax invoice.** What a valid invoice must carry depends on the
country of the company that sells DORY, typically:

- the seller's legal name, address and registration or tax number
  (`BILLING_SELLER_NAME` holds only a name today);
- the buyer's legal name and address (and tax number for businesses);
- tax rate and amount per line, or the legal reason for none;
- issue date, sequential number (exists), currency, and sometimes the
  exchange rate;
- an electronic invoicing format or registration with the tax authority in
  some countries.

**To do with an accountant or lawyer in the seller's country:**

- the invoice contents above, and whether a PDF must be sent to the buyer;
- terms of sale, refund and cancellation policy (DORY gives no refunds and
  keeps paid time until the period ends: the terms must say so);
- how long payment records must be kept (section 10);
- for card payments: the bank's merchant agreement and its PCI DSS
  questionnaire (docs/PAYMENT_GATEWAY_CHECKLIST.md).

## Short list before launch

| # | Item | Effort |
|---|---|---|
| 1 | Production `.env` (section 1), `config:cache`, trusted proxies | Configuration |
| 2 | Secrets stored outside the server and rotated per environment | Process |
| 3 | Daily off-site backups and a tested restore | Hosting |
| 4 | Cron for the scheduler; supervisor for queue workers and Reverb | Hosting |
| 5 | TLS for HTTPS and WSS, HSTS, HTTP redirect | Hosting |
| 6 | Log rotation, error monitoring, uptime checks and alerts | Outside service (free tiers exist) |
| 7 | Account deletion in the app | **New code** |
| 8 | Privacy policy, terms of sale, refund policy | Legal |
| 9 | Retention periods and clean-up commands | Decision, then code |
| 10 | Invoice contents and numbering (a continuous sequence per year, if required) for the seller's country | Legal, then code |
| 11 | Payment gateway checklist, if online payment is wanted | docs/PAYMENT_GATEWAY_CHECKLIST.md |

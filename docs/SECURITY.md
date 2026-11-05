# Security

Security baseline of the DORY API as of Phase 02. Each control is
implemented and covered by tests unless marked *planned*.

## Authentication

- Passwords are hashed with bcrypt (`BCRYPT_ROUNDS=12`, via the `hashed`
  cast) and re-hashed automatically when the cost changes.
- Password policy: 8–128 characters with upper and lower case letters and a
  number. In production, breached passwords are also rejected (Have I Been
  Pwned range API; only a 5-character hash prefix leaves the server).
- Sanctum tokens: only a SHA-256 hash is stored. Tokens expire (30 days by
  default), are revocable per device, all are revoked on password reset,
  and they carry the `dory_` prefix for secret scanning.
- No account enumeration: login returns the same 401 for an unknown email and
  for a wrong password, with equalized timing. Forgot-password always returns
  200. Reset gives the same error for an unknown email and for a bad token.
- Suspended or deactivated accounts cannot log in, and their existing tokens
  are rejected (403).
- Brute-force protection: login and registration are limited to 5/min per
  email+IP and 20/min per IP. Password reset is limited to 3/min per
  email+IP. All other endpoints: 60/min per user.

## Authorization and privilege escalation

- `is_platform_admin`, `status`, `public_id`, `email_verified_at`, and
  `last_login_at` are **prohibited** inputs on registration and profile
  update (422). They are not mass assignable either (`#[Fillable]`
  allow-lists).
- Profile endpoints have no user id parameter, so users can only modify
  themselves.
- Tenant isolation: see [MULTI_TENANCY.md](MULTI_TENANCY.md). Business
  access is resolved only through the caller's active membership, never
  from client-supplied ids.

## Data exposure

- API responses use Resources with explicit fields. The password hash and
  internal numeric ids are never returned; public ULIDs are used instead.
- `APP_DEBUG=false` in production: error responses contain only a generic
  message and a code. With debug on (local), a limited `debug` object is added
  (exception class, message, file:line), never a stack trace.
- The health endpoint reports only `ok`/`fail` per dependency. Error details
  go to the log.

## Logging

- Every log line carries the request's `request_id` (`X-Request-Id`).
- Request bodies, passwords and tokens are never logged. Laravel excludes
  `password`, `password_confirmation`, and `current_password` from exception
  context. Expected client errors (`ApiException`) are not logged as errors.
- `MAIL_MAILER=log` (local only) writes emails, **including reset links**,
  to a separate `storage/logs/mail-*.log` (kept 3 days). **Never use the log
  mailer in production.**
- Tests log to the `null` channel.

## Transport and headers

- Responses set `X-Content-Type-Options: nosniff`, `X-Frame-Options: DENY`,
  and `Referrer-Policy: no-referrer`.
- TLS and HSTS are the job of the reverse proxy or load balancer in front of
  the app (*deployment phase*).
- CORS: no browser origin is allowed by default (the mobile app does not
  need CORS). Allowed web origins are set explicitly in
  `CORS_ALLOWED_ORIGINS`, and credentials are never allowed cross-origin.

## Secrets and configuration

- `.env` is git-ignored. `.env.example` contains no secrets.
- The local database uses a dedicated least-privilege user (`dory_app`, with
  rights only on `dory_db` and `dory_db_testing`), not `root`. Its password
  lives only in `backend/.env`.
- No hardcoded passwords: factories generate a random password per test run,
  and demo seeding requires `SEED_DEMO_PASSWORD` and never runs in production.

## Production checklist

| Setting | Production value |
|---|---|
| `APP_ENV` | `production` |
| `APP_DEBUG` | `false` |
| `APP_KEY` | unique, generated, stored in the secret manager |
| `DB_*` | MySQL 8.4 LTS, dedicated user with least privilege, TLS if remote |
| `CACHE_STORE` / `QUEUE_CONNECTION` | `redis` |
| `REDIS_URL` | `tls://user:password@host:port` (managed Redis with ACL + TLS) |
| `MAIL_MAILER` | a real provider (SMTP/SES/Postmark…), **not** `log` |
| `PASSWORD_RESET_URL` | the app's deep link / web page |
| `CORS_ALLOWED_ORIGINS` | only trusted web origins |
| `LOG_LEVEL` | `info` or `warning` |
| `SEED_DEMO_PASSWORD` | unset |
| Scheduler | cron running `php artisan schedule:run` every minute (prunes expired tokens and reset tokens) |
| Caching | `php artisan optimize` on deploy (config, routes, events verified cacheable) |

## Known gaps (planned)

- Email verification endpoints, with a configured mail provider.
- Arabic translations of API and validation messages.
- Audit log for privileged actions (platform admin, membership changes).
- Two-factor authentication / phone OTP (depends on the Phase 03+ auth
  method decision).
- Dependency auditing in CI (`composer audit` currently run manually: no
  advisories at Phase 02).

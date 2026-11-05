# API Authentication

DORY uses **Laravel Sanctum personal access tokens** (bearer tokens) for
the mobile app. Base URL: `{APP_URL}/api/v1`. Every request and response is
JSON (see [API_CONVENTIONS.md](API_CONVENTIONS.md)).

## Tokens

- Format: `{id}|dory_{secret}`. The `dory_` prefix lets secret scanners
  (e.g. GitHub) recognise leaked tokens.
- Send it as `Authorization: Bearer <access_token>`.
- Only the SHA-256 hash of the secret is stored. The plain token appears
  **once**, in the register/login response, and is never logged.
- Lifetime: `SANCTUM_TOKEN_EXPIRATION` minutes (default 43200 = 30 days).
  `expires_at` is returned with the token. Expired tokens are rejected
  (401) and pruned daily by the scheduler.
- One token per device (`device_name`). Logout revokes only the current
  device's token. A password reset revokes all of the user's tokens.
- Suspended or deactivated accounts are rejected with 403
  `account_inactive`, even with a valid token.

## Endpoints

| Method | Path | Auth | Rate limit |
|---|---|---|---|
| POST | `/auth/register` | none | `auth` |
| POST | `/auth/login` | none | `auth` |
| POST | `/auth/logout` | bearer | `api` |
| GET | `/auth/me` | bearer | `api` |
| POST | `/auth/forgot-password` | none | `password-reset` |
| POST | `/auth/reset-password` | none | `password-reset` |
| PUT | `/users/profile` | bearer | `api` |
| PUT | `/users/locale` | bearer | `api` |
| GET | `/health` | none | `api` |

Rate limits (per minute, configurable in `.env`):

- `auth`: 5 per email+IP **and** 20 per IP.
- `password-reset`: 3 per email+IP and 20 per IP.
- `api`: 60 per user (or per IP when unauthenticated).

Exceeding a limit returns 429 `too_many_requests` with a `Retry-After`
header.

### POST /auth/register

```json
{
  "name": "Sara Haddad",
  "email": "sara@example.com",
  "password": "Queue-Ready-2026",
  "password_confirmation": "Queue-Ready-2026",
  "device_name": "Pixel 8",
  "phone": "+963 912 345 678",
  "locale": "ar",
  "timezone": "Asia/Damascus"
}
```

- Required: `name` (2–120), `email`, `password` + `password_confirmation`,
  `device_name` (≤100).
- Optional: `phone` (E.164; spaces, dashes and parentheses are stripped),
  `locale` (`en`|`ar`, default `en`), `timezone` (IANA, default `UTC`).
- Password policy: 8–128 characters with upper and lower case letters and a
  number. In production, passwords known from data breaches are also rejected.
- `is_platform_admin`, `status`, `public_id`, `email_verified_at`, and
  `last_login_at` are **prohibited**: sending any of them returns 422.
- Emails are normalised (trimmed, lower-cased); duplicates return 422 on
  `email`.

**201** response:

```json
{
  "success": true,
  "message": "Account created successfully",
  "data": {
    "user": {
      "id": "01jbz7q6v4n8x2k3m5p7r9t1w3",
      "name": "Sara Haddad",
      "email": "sara@example.com",
      "phone": "+963912345678",
      "locale": "ar",
      "timezone": "Asia/Damascus",
      "status": "active",
      "is_platform_admin": false,
      "email_verified_at": null,
      "last_login_at": null,
      "created_at": "2026-11-05T12:14:39+00:00"
    },
    "token": {
      "access_token": "1|dory_…",
      "token_type": "Bearer",
      "expires_at": "2026-12-05T12:14:39+00:00"
    }
  },
  "meta": null
}
```

### POST /auth/login

Body: `email`, `password`, `device_name`. Returns **200** with the same
shape as register.

| Situation | Status | code |
|---|---|---|
| Wrong password **or** unknown email (same message, same timing) | 401 | `invalid_credentials` |
| Suspended / deactivated account | 403 | `account_inactive` |
| Missing fields | 422 | `validation_failed` |
| Too many attempts | 429 | `too_many_requests` |

`last_login_at` is updated on success. Outdated password hashes are
re-hashed transparently.

### POST /auth/logout

Revokes the token used for the request. **200**, `data: null`.

### GET /auth/me

The current user plus their business memberships:

```json
"memberships": [
  {
    "business": { "id": "01jbz…", "name": "Shifa Clinic", "slug": "shifa-clinic", "status": "active" },
    "role": "owner",
    "status": "active",
    "joined_at": "2026-11-05T12:00:00+00:00"
  }
]
```

The Flutter app can derive the customer/business workspaces from this
response. Real authorization always happens on the server.

### POST /auth/forgot-password

Body: `email`. **Always 200** with the same message, whether or not the
account exists (no account enumeration). If it exists, a reset email is sent
whose link is:

```
{PASSWORD_RESET_URL}?token=…&email=…
```

`PASSWORD_RESET_URL` should point to the Flutter deep link or web page that
collects the new password. Reset tokens are stored hashed and expire after 60
minutes. Laravel also limits link requests to one per 60 seconds per account.

> **Email delivery is not configured yet.** Locally `MAIL_MAILER=log` writes
> emails to `storage/logs/mail-*.log`; nothing is sent. Configure a real mail
> provider before relying on password reset.

### POST /auth/reset-password

Body: `token`, `email`, `password`, `password_confirmation`.

- **200** on success. The password is changed and **all tokens are revoked**,
  so every device must log in again.
- An invalid or expired token **or** an unknown email returns the same 422
  error on `token`.

### PUT /users/profile

Partial update of the caller's own profile. Accepted fields: `name`,
`phone`, `timezone`. At least one is required (otherwise 422 on `profile`).

- `email`, `password`, `is_platform_admin`, `status`, `public_id`, and
  `email_verified_at` are **prohibited** (422). Email changes will need a
  re-verification flow; password changes use the reset flow.
- The route has no user id: a user can only change their own record.

### PUT /users/locale

Body: `locale` (`en` | `ar`). **200** with the updated user.

## Email verification (architecture only)

`users.email_verified_at` exists, and registration dispatches Laravel's
`Registered` event. The verification endpoints and the `MustVerifyEmail`
contract will be enabled together with a configured mail provider. Until then
no verification email is sent, and no endpoint requires a verified email.

## Flutter integration (later phase)

The Flutter app is **not** connected yet. When it is: store the token in
`flutter_secure_storage`, send `Authorization`, `Accept: application/json`
and `Accept-Language`, handle 401 (clear the session) and 403
`account_inactive`, and branch on the `code` field rather than on message
text.

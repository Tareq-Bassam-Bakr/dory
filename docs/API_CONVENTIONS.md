# API Conventions

## Versioning

- All endpoints live under **`/api/v1/`**. Requests without the version
  return 404.
- v1 is never changed incompatibly. Breaking changes ship as `/api/v2`, with
  v1 kept until clients migrate.
- Additive changes (new optional fields, new endpoints) are allowed in v1.
  Clients must ignore unknown fields.

## Requests

- JSON bodies with `Content-Type: application/json`.
- Responses are always JSON, even without `Accept: application/json`
  (enforced by `ForceJsonResponse`).
- Authentication: `Authorization: Bearer <token>` (see
  [API_AUTHENTICATION.md](API_AUTHENTICATION.md)).
- Identifiers in URLs and bodies are **public ULIDs**, never internal
  numeric ids.
- Field names are `snake_case`. Timestamps are ISO-8601 in UTC
  (`2026-11-05T12:14:39+00:00`).

## Response envelope

Every response is built by `App\Support\ApiResponse`.

**Success**

```json
{
  "success": true,
  "message": "Operation completed successfully",
  "data": {},
  "meta": null
}
```

**Error**

```json
{
  "success": false,
  "message": "Validation failed",
  "code": "validation_failed",
  "errors": {
    "email": ["The email field is required."]
  }
}
```

- `code` is a stable, machine-readable error identifier. **Clients branch on
  `code`, never on `message`**. Messages are for humans and may change or be
  translated.
- `errors` holds field errors for validation failures and is `null`
  otherwise.
- `details` is present on some errors only, with machine-readable context.
  For example a plan limit (Phase 08, [SUBSCRIPTIONS.md](SUBSCRIPTIONS.md#entitlements)):
  `"details": {"limit": "branches", "max": 1, "used": 1, "plan": "free"}`.
- With `APP_DEBUG=true` (local only), server errors add a `debug` object:
  exception class, message, and file:line. There is never a stack trace, and
  nothing is added in production.

## Status codes and error codes

| HTTP | code | When |
|---|---|---|
| 200 | – | Success |
| 201 | – | Resource created (registration) |
| 401 | `unauthenticated` | Missing, invalid, expired or revoked token |
| 401 | `invalid_credentials` | Login failed |
| 403 | `account_inactive` | Account suspended or deactivated |
| 403 | `forbidden` | Authenticated but not allowed (policy denied) |
| 404 | `not_found` | Unknown route or resource |
| 404 | `business_not_found` | Business does not exist **or** caller is not an active member |
| 405 | `method_not_allowed` | Wrong HTTP method |
| 409 | `membership_exists` | Duplicate business membership |
| 403 | `plan_limit_reached` | The business's plan does not allow adding one more (`details`). See [SUBSCRIPTIONS.md](SUBSCRIPTIONS.md#business-api) for every billing code |
| 403 | `feature_not_in_plan` | The business's plan does not include the feature (`details`) |
| 422 | `validation_failed` | Invalid input (`errors` lists fields) |
| 429 | `too_many_requests` | Rate limit hit (`Retry-After` header) |
| 500 | `server_error` | Unexpected failure (details only in logs) |
| 503 | `service_unavailable` | Maintenance mode or unavailable dependency |

## Pagination

List endpoints (from Phase 03) use `?page=` and `?per_page=` (default 15,
maximum 100, see `App\Support\Pagination`) and respond with
`ApiResponse::paginated()`:

```json
{
  "success": true,
  "message": "Operation completed successfully",
  "data": [ { "id": "01jbz…" } ],
  "meta": {
    "pagination": { "current_page": 1, "per_page": 15, "total": 42, "last_page": 3 }
  }
}
```

## Correlation id

Every response carries an `X-Request-Id` header. A client or gateway may send
its own id (8–64 characters, `A-Z a-z 0-9 . _ -`). Otherwise a UUID is
generated. The id is attached to every log line of the request, so include it
when reporting a problem.

## Security headers

All responses include `X-Content-Type-Options: nosniff`,
`X-Frame-Options: DENY`, and `Referrer-Policy: no-referrer`.

## Health check

`GET /api/v1/health` (no auth) returns per-dependency status:

```json
{
  "success": true,
  "message": "Service is healthy",
  "data": {
    "status": "ok",
    "api_version": "v1",
    "timestamp": "2026-11-05T12:14:38+00:00",
    "checks": { "database": "ok", "cache": "ok", "redis": "not_used" }
  },
  "meta": null
}
```

- Each check is `ok`, `fail`, or `not_used` (Redis is checked only when the
  cache, queue or session uses it).
- When any check fails: HTTP **503**, `success: false`, `status:
  "unhealthy"`, with the same `data.checks`. This is the one failure response
  that carries `data`, so monitoring can see which dependency failed. Error
  details go only to the log.
- `GET /up` is Laravel's minimal liveness probe (no dependency checks).

## Localization of messages

API messages are English in Phase 02 (`lang/en/api.php`). The Flutter app
shows its own localized text based on `code`. Arabic API messages and
validation translations can be added later without changing the contract.

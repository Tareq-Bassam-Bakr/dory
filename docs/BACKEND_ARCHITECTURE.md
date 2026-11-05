# Backend Architecture

The DORY API is a **Laravel 13** application in [`backend/`](../backend). It sits
next to the Flutter app in the same repository and shares nothing with it at
build time.

| | Version (Phase 02) |
|---|---|
| Laravel | 13.40.0 |
| PHP | 8.4.27 (Laravel 13 supports 8.3–8.5) |
| Laravel Sanctum | 4.3.3 |
| PHPUnit | 12.5 |
| Database | MySQL (production), MariaDB 10.4 (local XAMPP), SQLite in-memory (tests) |

## Request lifecycle

```
HTTP request
 → AssignRequestId          (global: X-Request-Id + log context)
 → api group: ForceJsonResponse, throttle:api, SubstituteBindings
 → route middleware: throttle:auth | auth:sanctum + active | business.context
 → FormRequest              (normalize + validate; privileged fields rejected)
 → Controller (thin)        → Service (business logic, transactions)
 → API Resource             (public shape: ULIDs, no secrets)
 → ApiResponse envelope     → SetSecurityHeaders (global)
Exceptions anywhere → ApiExceptionRenderer → standard error envelope
```

## Folder structure

```
backend/app/
├── Enums/                 UserStatus, BusinessStatus, BusinessRole, MembershipStatus, AppLocale
├── Exceptions/            ApiException (+ subclasses), ApiExceptionRenderer
├── Http/
│   ├── Controllers/Api/V1/{Auth,Users,System}/   thin controllers
│   ├── Middleware/        AssignRequestId, ForceJsonResponse, SetSecurityHeaders,
│   │                      EnsureUserIsActive, ResolveBusinessContext
│   ├── Requests/{Auth,Users}/  FormRequests (+ Concerns/GuardsAccountInput)
│   └── Resources/         UserResource, BusinessResource, BusinessMembershipResource, AuthTokenResource
├── Models/                User, Business, BusinessMember
├── Policies/              BusinessPolicy
├── Providers/             AppServiceProvider (rate limits, password rules, strict models)
├── Services/
│   ├── Auth/              AuthService, PasswordResetService, AuthResult
│   ├── Business/          BusinessMembershipService, CurrentBusinessContext
│   ├── Users/             ProfileService
│   └── System/            HealthCheckService
└── Support/               ApiResponse, Pagination
```

## Rules

1. **Controllers stay thin.** They take a validated FormRequest, call one
   service method, and return a Resource through `ApiResponse`. No queries and
   no business rules.
2. **Business logic lives in services.** Services receive dependencies
   through constructor injection, own their database transactions, and throw
   typed exceptions (`ApiException` subclasses) for expected failures.
3. **Every input is validated** by a FormRequest. Fields a client must never
   set (`is_platform_admin`, `status`, `public_id`, ...) are `prohibited`, so
   an attempt fails with 422 instead of being silently ignored.
4. **No insecure mass assignment.** Models declare `#[Fillable]` explicitly.
   `business_id`/`user_id` on memberships are not fillable; the membership
   service associates them. `Model::shouldBeStrict()` is enabled outside
   production, so lazy loading, discarded attributes, and missing attributes
   fail loudly.
5. **Public identifiers only.** Models use an auto-increment `id` internally
   and a ULID `public_id` externally. Resources map `public_id` to `id`, and
   route model binding uses `public_id`.
6. **Database writes never fail silently.** Services use `saveOrFail()` and
   `DB::transaction()`. Exceptions propagate and become a 500 envelope, never
   a success response.
7. **Laravel conventions first.** Use framework features (FormRequests,
   Resources, Policies, the password broker, RateLimiter) before writing custom
   infrastructure.

## Decisions

| Decision | Reason |
|---|---|
| PHP 8.4.27 installed side by side in `D:\php84` | XAMPP ships PHP 8.2, below Laravel 13's minimum. XAMPP stays untouched. |
| Sanctum **token** auth only (no SPA cookies) | Mobile client. `statefulApi()` is not enabled, so no CSRF or session surface. |
| `SESSION_DRIVER=array`, no `sessions` table | The API is stateless. |
| Explicit error `code` in error responses | Clients branch on stable codes and localize messages themselves. |
| Removed `laravel/pail`, `laravel/pao`, and the Vite/npm frontend tooling | Unused: Pail needs PCNTL (unavailable on Windows), Pao only changes test output for AI agents, and the backend serves no frontend. |
| Removed the skeleton's `AGENTS.md` / `CLAUDE.md` | They instructed AI agents to pipe a remote installer script into PowerShell and to install an extra package. |
| No Redis client package yet | No Redis server exists locally. Production uses the phpredis extension. See [LOCAL_BACKEND_SETUP.md](LOCAL_BACKEND_SETUP.md#redis). |
| Laravel Reverb, FCM, and AI services not installed | Later phases (Reverb: Phase 06). |

## Extending the API (later phases)

- New endpoint: FormRequest → service method → Resource → route in
  `routes/api.php` under `v1`, plus feature tests for the happy path,
  validation, authorization, and tenant isolation.
- Business-scoped endpoint: route under `businesses/{business}` with
  `auth:sanctum`, `active`, and `business.context`. Read the business from
  `CurrentBusinessContext` and authorize with `BusinessPolicy`. See
  [MULTI_TENANCY.md](MULTI_TENANCY.md).
- Breaking change: add `/api/v2` routes, never change v1 behaviour.

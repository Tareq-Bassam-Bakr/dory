# Multi-Tenancy

DORY is a multi-tenant SaaS: **each business is a tenant**. Phase 02 built
the isolation foundation; Phase 03 built business, branch, service, team and
invitation management on it, with branch-level scoping.

## Model

```
User ──< BusinessMember >── Business ──< Branch ──< Service
           role:          owner | manager | employee
           status:        invited | active | suspended
           branch_access: all | selected ──< business_member_branch >── Branch
```

- A user can be a member of many businesses with a different role in each
  (owns one, works in another).
- One membership per user per business (`UNIQUE(business_id, user_id)`).
- **Only `active` memberships grant access.** Invited and suspended members
  have none.
- Every business has an owner: `createBusinessWithOwner()` creates both in
  one transaction.
- **Platform administration is separate.** `users.is_platform_admin` is
  independent of memberships, cannot be set through any API input, and does
  not grant business-management rights (admins may only *view* businesses).

## Rules for every business-scoped feature

1. **Never trust a client-supplied business id.** The business is resolved
   from the route's public id **through the authenticated user's own active
   memberships**. Query strings, bodies and headers such as `business_id` or
   `X-Business-Id` are ignored for tenancy.
2. **Non-members get 404 `business_not_found`**, the same response as for a
   non-existent business, so users cannot discover other tenants.
3. **Authorize every action** with `BusinessPolicy` (or a future policy per
   resource), using the role from the membership in *that* business.
4. **Scope every query** to the resolved business:
   `$context->business()->branches()->…`, never `Branch::find($id)`.
   Listing businesses uses `Business::query()->accessibleBy($user)`.
5. **Flutter role checks are UX only.** The server always decides.

## How a request reaches tenant data

Route parameters are resolved by **tenant route bindings**
(`AppServiceProvider::configureRouteBindings` → `TenantResolver`):

| Parameter | Resolved through |
|---|---|
| `{business}` | the caller's active membership in that business |
| `{branch}` | an accessible business **and** the membership's branch access |
| `{service}` | its branch, as above |
| `{member}`, `{invitation}` | the business already resolved for the request |
| `{invitationToken}` | the SHA-256 of the token; the business must still exist |

Every lookup starts from the authenticated user, so other tenants' records
are never loaded, and "not allowed" is the same 404 (same body) as "not
found". Parameters only match ULIDs (64 characters for tokens).
`EnsureUserIsActive` runs right after authentication, before any binding.

The bindings fill the request-scoped `CurrentBusinessContext`; policies
(`BusinessPolicy`, `BranchPolicy`, `ServicePolicy`) then ask
`BusinessAbilities` whether the membership may act. A policy can deny with
an explicit code (`business_suspended`), which `ApiExceptionRenderer`
renders as is. Form requests authorize **before** validating.

## Building blocks

| Component | Location | Purpose |
|---|---|---|
| `BusinessMembershipService` | `app/Services/Business` | Create businesses with an owner, add members (duplicate-safe, branch assignment limited to the business), look up memberships. |
| `TenantResolver` | `app/Services/Business` | The route bindings above. |
| `CurrentBusinessContext` | `app/Services/Business` | Request-scoped membership of the resolved business: `business()`, `role()`, `membership()`, `abilities()`. |
| `BusinessAbilities` | `app/Services/Business` | The permission matrix below; also the `permissions` hints in responses. |
| `BusinessPolicy`, `BranchPolicy`, `ServicePolicy` | `app/Policies` | Authorization; non-members are told the resource does not exist. |
| `ResolveBusinessContext` (`business.context`) | `app/Http/Middleware` | Phase 02 middleware, still available for routes that need it. |
| `Business::accessibleBy($user)` | `app/Models/Business` | Query scope for listings. |

## Permission matrix (Phase 03)

| Ability | Owner | Manager, all branches | Manager, selected branches | Employee | Non-member | Platform admin without membership |
|---|:-:|:-:|:-:|:-:|:-:|:-:|
| View business | ✓ | ✓ | ✓ | ✓ | 404 | view policy only¹ |
| Update profile | ✓ | ✓ | ✗ | ✗ | 404 | ✗ |
| Activate / deactivate | ✓ | ✗ | ✗ | ✗ | 404 | ✗ |
| Delete business | ✓ | ✗ | ✗ | ✗ | 404 | ✗ |
| Create / delete branches | ✓ | ✓ | ✗ | ✗ | 404 | ✗ |
| View branches | all | all | assigned | per access | 404 | ✗ |
| Update branch, manage its services | ✓ | ✓ | assigned | ✗ | 404 | ✗ |
| View team and invitations | ✓ | ✓ | ✓ | ✗ | 404 | ✗ |
| Invite, change roles, remove, revoke | ✓ | ✗ | ✗ | ✗ | 404 | ✗ |

On a **suspended** business every write returns `403 business_suspended`;
reads keep working. Archived (deleted) businesses are unreachable.

¹ `BusinessPolicy::view` still allows platform admins (Phase 02 decision),
but the management endpoints resolve businesses through memberships, so an
admin without a membership gets 404 there. Support views for admins belong
to a dedicated admin API (later phase).

## Tested guarantees

`tests/Feature/Tenancy/` covers:

- A member resolves their own business. A user from another tenant gets 404.
  A non-existent business gets the same 404.
- Smuggled `business_id` / `X-Business-Id` values are ignored.
- Suspended and invited memberships grant nothing.
- Duplicate memberships are rejected by the service **and** the database.
- Memberships belong to the right user and business, and a user can own one
  business and work in another.
- The policy matrix above, including platform admin independence.

Phase 03 adds `tests/Feature/Security/TenantIsolationTest.php`: all 20
tenant-scoped endpoints attacked by an owner of another business (404
identical to missing, no data changed), smuggled ids in queries, bodies
and headers, branch managers crossing to sibling branches, and the
platform-admin flag granting nothing. Feature tests per resource live in
`tests/Feature/Api/V1/Businesses` and `tests/Feature/Api/V1/Members`.

## Not yet built

Platform administration endpoints (suspending businesses, support views),
queue data, and audit-log reading.

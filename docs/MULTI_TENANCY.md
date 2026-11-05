# Multi-Tenancy

DORY is a multi-tenant SaaS: **each business is a tenant**. Phase 02
provides the isolation foundation. Business management endpoints arrive in
Phase 03 and must be built on it.

## Model

```
User ──< BusinessMember >── Business
           role:   owner | manager | employee
           status: invited | active | suspended
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

## Building blocks

| Component | Location | Purpose |
|---|---|---|
| `BusinessMembershipService` | `app/Services/Business` | Create businesses with an owner, add members (duplicate-safe), look up memberships and roles. Every lookup starts from the user. |
| `CurrentBusinessContext` | `app/Services/Business` | Request-scoped holder of the resolved membership: `business()`, `role()`, `membership()`. Throws if read before it is resolved. |
| `ResolveBusinessContext` middleware (`business.context`) | `app/Http/Middleware` | Resolves `{business}` from the route via the service; 404 for non-members. |
| `BusinessPolicy` | `app/Policies` | `view` (active member or platform admin), `update` (owner, manager), `manageMembers` (owner). |
| `Business::accessibleBy($user)` | `app/Models/Business` | Query scope: only businesses where the user is an active member. |
| `BusinessRole` | `app/Enums` | `canManageBusiness()`, `canManageMembers()`. |

## Permission matrix (Phase 02)

| Ability | Owner | Manager | Employee | Non-member | Suspended member | Platform admin (no membership) |
|---|:-:|:-:|:-:|:-:|:-:|:-:|
| view | ✓ | ✓ | ✓ | ✗ | ✗ | ✓ |
| update | ✓ | ✓ | ✗ | ✗ | ✗ | ✗ |
| manageMembers | ✓ | ✗ | ✗ | ✗ | ✗ | ✗ |

## Example: a Phase 03 business route

```php
Route::prefix('v1/businesses/{business}')
    ->middleware(['auth:sanctum', 'active', 'business.context'])
    ->group(function () {
        Route::put('/', [BusinessController::class, 'update']);
    });

// Controller
public function update(UpdateBusinessRequest $request, CurrentBusinessContext $context)
{
    $business = $context->business();          // resolved via membership
    $this->authorize('update', $business);     // BusinessPolicy
    // ... service call scoped to $business
}
```

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

## Not in Phase 02

Business CRUD endpoints, member invitations, branch-level scoping (branch
managers limited to their branches), and business status enforcement
(suspended businesses) are designed for, but implemented in later phases.

<?php

namespace App\Policies;

use App\Models\Business;
use App\Models\User;
use App\Services\Business\BusinessMembershipService;

/**
 * Authorization for business (tenant) resources.
 *
 * Business roles come only from an active membership in *this* business.
 * Platform administrators may view any business for support purposes, but
 * that privilege never turns into business management rights.
 */
class BusinessPolicy
{
    public function __construct(private readonly BusinessMembershipService $memberships) {}

    public function view(User $user, Business $business): bool
    {
        return $user->isPlatformAdmin() || $this->memberships->hasActiveMembership($user, $business);
    }

    public function update(User $user, Business $business): bool
    {
        return $this->memberships->roleOf($user, $business)?->canManageBusiness() ?? false;
    }

    public function manageMembers(User $user, Business $business): bool
    {
        return $this->memberships->roleOf($user, $business)?->canManageMembers() ?? false;
    }
}

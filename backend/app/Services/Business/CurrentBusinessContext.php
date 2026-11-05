<?php

namespace App\Services\Business;

use App\Enums\BusinessRole;
use App\Exceptions\BusinessNotFoundException;
use App\Models\Business;
use App\Models\BusinessMember;
use App\Models\User;
use LogicException;

/**
 * The business the current request acts on, resolved from the authenticated
 * user's own active membership.
 *
 * Registered as a scoped binding, so each request starts empty. It is filled
 * by the tenant route bindings ({business}, {branch}, {service}: see
 * TenantResolver) or by the `business.context` middleware. Controllers then
 * read business()/role() instead of trusting any business id sent by the
 * client.
 */
final class CurrentBusinessContext
{
    private ?BusinessMember $membership = null;

    public function __construct(private readonly BusinessMembershipService $memberships) {}

    /**
     * @throws BusinessNotFoundException When the business does not exist or the user is not an active member.
     */
    public function resolveForUser(User $user, string $businessPublicId): BusinessMember
    {
        $membership = $this->memberships->activeMembershipByBusinessPublicId($user, $businessPublicId);

        if ($membership === null) {
            throw new BusinessNotFoundException;
        }

        return $this->membership = $membership;
    }

    /**
     * Sets the context from a membership resolved elsewhere (TenantResolver).
     */
    public function use(BusinessMember $membership): BusinessMember
    {
        return $this->membership = $membership;
    }

    /**
     * The user's active membership in the business: from this context when
     * it was resolved for that user and business, otherwise from the
     * database. Null for non-members.
     */
    public function membershipFor(User $user, Business $business): ?BusinessMember
    {
        if ($this->membership !== null
            && $this->membership->user_id === $user->getKey()
            && $this->membership->business_id === $business->getKey()
            && $this->membership->grantsAccess()) {
            return $this->membership;
        }

        return $this->memberships->findMembership($user, $business);
    }

    public function isResolved(): bool
    {
        return $this->membership !== null;
    }

    public function business(): Business
    {
        return $this->membership()->business;
    }

    public function role(): BusinessRole
    {
        return $this->membership()->role;
    }

    public function membership(): BusinessMember
    {
        return $this->membership
            ?? throw new LogicException('Business context was not resolved. Add the business.context middleware to the route.');
    }

    public function abilities(): BusinessAbilities
    {
        return BusinessAbilities::for($this->business(), $this->membership());
    }
}

<?php

namespace App\Policies;

use App\Models\Business;
use App\Models\User;
use App\Services\Business\BusinessAbilities;
use App\Services\Business\CurrentBusinessContext;
use Closure;
use Illuminate\Auth\Access\Response;

/**
 * Authorization for business (tenant) resources. The permission matrix
 * itself lives in BusinessAbilities.
 *
 * Business roles come only from an active membership in *this* business.
 * Non-members are told the business does not exist (404). Platform
 * administrators may view any business for support purposes, but that
 * privilege never turns into business management rights.
 */
class BusinessPolicy
{
    public function __construct(private readonly CurrentBusinessContext $context) {}

    public function view(User $user, Business $business): Response
    {
        if ($user->isPlatformAdmin() || $this->abilities($user, $business)->isMember()) {
            return Response::allow();
        }

        return self::notFound();
    }

    public function update(User $user, Business $business): Response
    {
        return $this->decide($user, $business, fn (BusinessAbilities $a) => $a->canUpdate(), write: true);
    }

    /**
     * Insights and peak times (docs/AI_ARCHITECTURE.md): owners and managers.
     */
    public function viewInsights(User $user, Business $business): Response
    {
        return $this->decide($user, $business, fn (BusinessAbilities $a) => $a->canViewInsights(), write: false);
    }

    /**
     * Plan, limits and usage (docs/SUBSCRIPTIONS.md): owners and managers.
     */
    public function viewSubscription(User $user, Business $business): Response
    {
        return $this->decide($user, $business, fn (BusinessAbilities $a) => $a->canViewSubscription(), write: false);
    }

    /**
     * Billing history: the owner, also while the business is suspended.
     */
    public function viewBilling(User $user, Business $business): Response
    {
        return $this->decide($user, $business, fn (BusinessAbilities $a) => $a->canViewBilling(), write: false);
    }

    /**
     * Buying, changing and cancelling plans: the owner.
     */
    public function manageBilling(User $user, Business $business): Response
    {
        return $this->decide($user, $business, fn (BusinessAbilities $a) => $a->canManageBilling(), write: true);
    }

    public function changeStatus(User $user, Business $business): Response
    {
        return $this->decide($user, $business, fn (BusinessAbilities $a) => $a->canChangeStatus(), write: true);
    }

    public function delete(User $user, Business $business): Response
    {
        return $this->decide($user, $business, fn (BusinessAbilities $a) => $a->canDelete(), write: true);
    }

    public function createBranch(User $user, Business $business): Response
    {
        return $this->decide($user, $business, fn (BusinessAbilities $a) => $a->canCreateBranches(), write: true);
    }

    public function viewMembers(User $user, Business $business): Response
    {
        return $this->decide($user, $business, fn (BusinessAbilities $a) => $a->canViewMembers(), write: false);
    }

    public function manageMembers(User $user, Business $business): Response
    {
        return $this->decide($user, $business, fn (BusinessAbilities $a) => $a->canManageMembers(), write: true);
    }

    public static function notFound(): Response
    {
        return Response::denyAsNotFound(__('api.business.not_found'), 'business_not_found');
    }

    public static function suspended(): Response
    {
        return Response::deny(__('api.business.suspended'), 'business_suspended');
    }

    private function abilities(User $user, Business $business): BusinessAbilities
    {
        return BusinessAbilities::for($business, $this->context->membershipFor($user, $business));
    }

    /**
     * @param  Closure(BusinessAbilities): bool  $check
     */
    private function decide(User $user, Business $business, Closure $check, bool $write): Response
    {
        $abilities = $this->abilities($user, $business);

        if (! $abilities->isMember()) {
            // Admins know the business exists (they may view it); everyone
            // else is told it does not.
            return $user->isPlatformAdmin() ? Response::deny() : self::notFound();
        }

        if ($write && ! $abilities->acceptsChanges()) {
            return self::suspended();
        }

        return $check($abilities) ? Response::allow() : Response::deny();
    }
}

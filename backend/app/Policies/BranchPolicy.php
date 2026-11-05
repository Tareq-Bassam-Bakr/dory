<?php

namespace App\Policies;

use App\Models\Branch;
use App\Models\User;
use App\Services\Business\BusinessAbilities;
use App\Services\Business\CurrentBusinessContext;
use Closure;
use Illuminate\Auth\Access\Response;

/**
 * Authorization for branches and, through manageServices, their services.
 * Users without access to the branch (other tenants, or members limited to
 * other branches) are told it does not exist.
 */
class BranchPolicy
{
    public function __construct(private readonly CurrentBusinessContext $context) {}

    public function view(User $user, Branch $branch): Response
    {
        return $this->abilities($user, $branch)->canViewBranch($branch)
            ? Response::allow()
            : self::notFound();
    }

    public function update(User $user, Branch $branch): Response
    {
        return $this->decide($user, $branch, fn (BusinessAbilities $a) => $a->canUpdateBranch($branch));
    }

    public function delete(User $user, Branch $branch): Response
    {
        return $this->decide($user, $branch, fn (BusinessAbilities $a) => $a->canDeleteBranch($branch));
    }

    public function manageServices(User $user, Branch $branch): Response
    {
        return $this->decide($user, $branch, fn (BusinessAbilities $a) => $a->canManageServices($branch));
    }

    public static function notFound(): Response
    {
        return Response::denyAsNotFound(__('api.branch.not_found'), 'branch_not_found');
    }

    private function abilities(User $user, Branch $branch): BusinessAbilities
    {
        $business = $branch->business;

        return BusinessAbilities::for($business, $this->context->membershipFor($user, $business));
    }

    /**
     * @param  Closure(BusinessAbilities): bool  $check
     */
    private function decide(User $user, Branch $branch, Closure $check): Response
    {
        $abilities = $this->abilities($user, $branch);

        if (! $abilities->canViewBranch($branch)) {
            return self::notFound();
        }

        if (! $abilities->acceptsChanges()) {
            return BusinessPolicy::suspended();
        }

        return $check($abilities) ? Response::allow() : Response::deny();
    }
}

<?php

namespace Tests\Concerns;

use App\Enums\BranchAccess;
use App\Enums\BusinessRole;
use App\Models\Branch;
use App\Models\Business;
use App\Models\BusinessCategory;
use App\Models\BusinessMember;
use App\Models\Service;
use App\Models\User;
use App\Services\Business\BusinessMembershipService;
use App\Services\Business\BusinessService;
use Illuminate\Testing\TestResponse;

/**
 * Builds businesses, members, branches and services the way the API does,
 * and sends requests as a given user.
 */
trait BuildsTenants
{
    /**
     * A business created through BusinessService (owner membership included).
     *
     * @param  array<string, mixed>  $attributes
     */
    protected function businessOwnedBy(User $owner, array $attributes = [], ?BusinessCategory $category = null): Business
    {
        [$business] = app(BusinessService::class)->create(
            $owner,
            $category ?? BusinessCategory::factory()->create(),
            $attributes + ['name' => 'Shifa Clinic'],
        );

        return $business;
    }

    /**
     * A new user with an active membership in the business.
     *
     * @param  list<Branch>  $branches  Assigned branches when access is "selected".
     */
    protected function memberOf(
        Business $business,
        BusinessRole $role,
        BranchAccess $access = BranchAccess::All,
        array $branches = [],
    ): User {
        $user = User::factory()->create();
        app(BusinessMembershipService::class)->addMember(
            $business,
            $user,
            $role,
            branchAccess: $access,
            branches: collect($branches),
        );

        return $user;
    }

    protected function membershipOf(User $user, Business $business): ?BusinessMember
    {
        return BusinessMember::query()
            ->where('user_id', $user->getKey())
            ->where('business_id', $business->getKey())
            ->first();
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    protected function branchOf(Business $business, array $attributes = []): Branch
    {
        return Branch::factory()->for($business)->create($attributes);
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    protected function serviceOf(Branch $branch, array $attributes = []): Service
    {
        return Service::factory()->for($branch)->create($attributes);
    }

    /**
     * Sends a JSON request authenticated as $user with a real Sanctum token.
     *
     * @param  array<string, mixed>  $data
     * @param  array<string, string>  $headers
     */
    protected function actingAsUser(User $user, string $method, string $uri, array $data = [], array $headers = []): TestResponse
    {
        // Guards cache the resolved user; forget it so each call authenticates on its own.
        $this->forgetAuthenticatedUser();

        return $this->withToken($this->tokenFor($user))->json($method, $uri, $data, $headers);
    }
}

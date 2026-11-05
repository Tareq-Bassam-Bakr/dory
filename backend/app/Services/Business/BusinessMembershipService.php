<?php

namespace App\Services\Business;

use App\Enums\BranchAccess;
use App\Enums\BusinessRole;
use App\Enums\MembershipStatus;
use App\Exceptions\DuplicateMembershipException;
use App\Models\Branch;
use App\Models\Business;
use App\Models\BusinessMember;
use App\Models\User;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * The single place where business memberships are created and checked.
 *
 * Every lookup starts from the authenticated user's own memberships, so a
 * caller can never reach a business just by knowing (or guessing) its id.
 */
final class BusinessMembershipService
{
    /**
     * Creates a business and makes the user its owner, atomically. A business
     * therefore never exists without an owner.
     */
    public function createBusinessWithOwner(
        User $owner,
        string $name,
        ?string $description = null,
        ?string $slug = null,
    ): Business {
        $business = new Business([
            'name' => $name,
            'slug' => $slug ?? self::uniqueSlug($name),
            'description' => $description,
        ]);

        return $this->persistWithOwner($business, $owner);
    }

    /**
     * Saves a new business and its owner membership in one transaction.
     */
    public function persistWithOwner(Business $business, User $owner): Business
    {
        return DB::transaction(function () use ($business, $owner): Business {
            $business->creator()->associate($owner);
            $business->saveOrFail();

            $this->addMember($business, $owner, BusinessRole::Owner);

            return $business;
        });
    }

    /**
     * @param  Collection<int, Branch>|null  $branches  Branches for "selected" access; must belong to the business.
     *
     * @throws DuplicateMembershipException When the user already has a membership in any state.
     */
    public function addMember(
        Business $business,
        User $user,
        BusinessRole $role,
        MembershipStatus $status = MembershipStatus::Active,
        BranchAccess $branchAccess = BranchAccess::All,
        ?Collection $branches = null,
    ): BusinessMember {
        if ($this->findMembership($user, $business, activeOnly: false) !== null) {
            throw new DuplicateMembershipException;
        }

        $membership = new BusinessMember([
            'role' => $role,
            'status' => $status,
            // Owners always reach every branch.
            'branch_access' => $role === BusinessRole::Owner ? BranchAccess::All : $branchAccess,
            'joined_at' => $status === MembershipStatus::Active ? now() : null,
        ]);
        $membership->business()->associate($business);
        $membership->user()->associate($user);

        try {
            DB::transaction(function () use ($membership, $business, $branches): void {
                $membership->saveOrFail();

                if ($membership->branch_access === BranchAccess::Selected && $branches !== null) {
                    $membership->branches()->sync(self::ownBranchIds($business, $branches));
                }
            });
        } catch (UniqueConstraintViolationException) {
            // Concurrent request created the same membership first.
            throw new DuplicateMembershipException;
        }

        return $membership;
    }

    public function findMembership(User $user, Business $business, bool $activeOnly = true): ?BusinessMember
    {
        return BusinessMember::query()
            ->where('user_id', $user->getKey())
            ->where('business_id', $business->getKey())
            ->when($activeOnly, fn ($query) => $query->active())
            ->first();
    }

    /**
     * Resolves an active membership from a business public id, searching only
     * within the user's own memberships. Returns null both when the business
     * does not exist and when the user is not an active member of it.
     */
    public function activeMembershipByBusinessPublicId(User $user, string $businessPublicId): ?BusinessMember
    {
        return BusinessMember::query()
            ->where('user_id', $user->getKey())
            ->active()
            ->whereHas('business', fn ($query) => $query->where('public_id', $businessPublicId))
            ->with('business.category')
            ->first();
    }

    public function hasActiveMembership(User $user, Business $business): bool
    {
        return $this->findMembership($user, $business) !== null;
    }

    /**
     * The user's role in the business, or null without an active membership.
     */
    public function roleOf(User $user, Business $business): ?BusinessRole
    {
        return $this->findMembership($user, $business)?->role;
    }

    /**
     * A slug that no business (including deleted ones) uses yet.
     */
    public static function uniqueSlug(string $name): string
    {
        // Names in Arabic or other scripts may not transliterate to ASCII.
        $base = Str::limit(Str::slug($name), 140, '') ?: 'business';
        $slug = $base;

        while (Business::withTrashed()->where('slug', $slug)->exists()) {
            $slug = $base.'-'.Str::lower(Str::random(6));
        }

        return $slug;
    }

    /**
     * Keeps only the ids of branches that belong to the business, so a
     * membership can never be attached to another tenant's branch.
     *
     * @param  Collection<int, Branch>  $branches
     * @return list<int>
     */
    private static function ownBranchIds(Business $business, Collection $branches): array
    {
        return $branches
            ->filter(fn (Branch $branch) => $branch->business_id === $business->getKey())
            ->map(fn (Branch $branch) => $branch->getKey())
            ->values()
            ->all();
    }
}

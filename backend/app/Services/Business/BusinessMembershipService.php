<?php

namespace App\Services\Business;

use App\Enums\BusinessRole;
use App\Enums\MembershipStatus;
use App\Exceptions\DuplicateMembershipException;
use App\Models\Business;
use App\Models\BusinessMember;
use App\Models\User;
use Illuminate\Database\UniqueConstraintViolationException;
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
        return DB::transaction(function () use ($owner, $name, $description, $slug): Business {
            $business = new Business([
                'name' => $name,
                'slug' => $slug ?? $this->uniqueSlug($name),
                'description' => $description,
            ]);
            $business->creator()->associate($owner);
            $business->saveOrFail();

            $this->addMember($business, $owner, BusinessRole::Owner);

            return $business;
        });
    }

    /**
     * @throws DuplicateMembershipException When the user already has a membership in any state.
     */
    public function addMember(
        Business $business,
        User $user,
        BusinessRole $role,
        MembershipStatus $status = MembershipStatus::Active,
    ): BusinessMember {
        if ($this->findMembership($user, $business, activeOnly: false) !== null) {
            throw new DuplicateMembershipException;
        }

        $membership = new BusinessMember([
            'role' => $role,
            'status' => $status,
            'joined_at' => $status === MembershipStatus::Active ? now() : null,
        ]);
        $membership->business()->associate($business);
        $membership->user()->associate($user);

        try {
            $membership->saveOrFail();
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
            ->with('business')
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

    private function uniqueSlug(string $name): string
    {
        // Names in Arabic or other scripts may not transliterate to ASCII.
        $base = Str::slug($name) ?: 'business';
        $slug = $base;

        while (Business::query()->where('slug', $slug)->exists()) {
            $slug = $base.'-'.Str::lower(Str::random(6));
        }

        return $slug;
    }
}

<?php

namespace App\Services\Business;

use App\Enums\AuditAction;
use App\Enums\BranchAccess;
use App\Enums\BusinessRole;
use App\Exceptions\ProtectedMembershipException;
use App\Models\Branch;
use App\Models\BusinessMember;
use App\Models\User;
use App\Services\Audit\AuditLogger;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * Changes and removes team memberships. Authorization (owner only) happens
 * before these methods are called; they additionally protect ownership:
 *
 * - The owner membership can never be changed or removed here.
 * - Nobody can change or remove their own membership here, so no member
 *   can promote themselves.
 * - The owner role cannot be granted: ownership transfer is not available.
 */
final class TeamService
{
    public function __construct(private readonly AuditLogger $audit) {}

    /**
     * @param  Collection<int, Branch>|null  $branches  Used when $branchAccess is "selected".
     */
    public function update(
        BusinessMember $member,
        User $actor,
        ?BusinessRole $role,
        ?BranchAccess $branchAccess,
        ?Collection $branches,
    ): BusinessMember {
        $this->ensureChangeable($member, $actor);

        if ($role === BusinessRole::Owner) {
            throw new InvalidArgumentException('The owner role cannot be granted.');
        }

        return DB::transaction(function () use ($member, $actor, $role, $branchAccess, $branches): BusinessMember {
            $before = ['role' => $member->role->value, 'branch_access' => $member->branch_access->value];

            if ($role !== null) {
                $member->role = $role;
            }

            if ($branchAccess !== null) {
                $member->branch_access = $branchAccess;
            }

            $member->saveOrFail();

            if ($member->branch_access === BranchAccess::All) {
                $member->branches()->detach();
            } elseif ($branchAccess === BranchAccess::Selected && $branches !== null) {
                $member->branches()->sync(
                    $branches->filter(fn (Branch $branch) => $branch->business_id === $member->business_id)
                        ->map(fn (Branch $branch) => $branch->getKey())->values()->all(),
                );
            }

            $this->audit->record($member->business, $actor, AuditAction::MemberUpdated, $member, [
                'member' => $member->public_id,
                'before' => $before,
                'after' => ['role' => $member->role->value, 'branch_access' => $member->branch_access->value],
            ]);

            return $member->load('branches');
        });
    }

    public function remove(BusinessMember $member, User $actor): void
    {
        $this->ensureChangeable($member, $actor);

        DB::transaction(function () use ($member, $actor): void {
            $this->audit->record($member->business, $actor, AuditAction::MemberRemoved, $member, [
                'member' => $member->public_id,
                'user' => $member->user->public_id,
                'role' => $member->role->value,
            ]);

            $member->deleteOrFail();
        });
    }

    private function ensureChangeable(BusinessMember $member, User $actor): void
    {
        if ($member->isOwner() || $member->user_id === $actor->getKey()) {
            throw new ProtectedMembershipException;
        }
    }
}

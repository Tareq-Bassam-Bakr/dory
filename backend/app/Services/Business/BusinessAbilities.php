<?php

namespace App\Services\Business;

use App\Enums\BusinessRole;
use App\Models\Branch;
use App\Models\Business;
use App\Models\BusinessMember;
use App\Models\Queue;
use App\Models\QueueStaffAssignment;

/**
 * What one membership may do in its business: the single source of the
 * permission matrix, used by the policies and by the `permissions` hints in
 * API responses (docs/MULTI_TENANCY.md).
 *
 * - Owner: everything. Always reaches every branch.
 * - Manager with "all" branch access: the business profile, all branches and
 *   services, and viewing the team. Cannot delete the business, change its
 *   status or manage members.
 * - Manager with "selected" access (branch manager): only the assigned
 *   branches and their services. Cannot create or delete branches.
 * - Employee: read-only, within their branch access.
 *
 * Writes are refused while the business does not accept changes
 * (suspended by the platform).
 */
final readonly class BusinessAbilities
{
    private function __construct(
        private Business $business,
        private ?BusinessMember $membership,
    ) {}

    /**
     * A membership of another business, or one that is not active, grants nothing.
     */
    public static function for(Business $business, ?BusinessMember $membership): self
    {
        $valid = $membership !== null
            && $membership->business_id === $business->getKey()
            && $membership->grantsAccess();

        return new self($business, $valid ? $membership : null);
    }

    public function isMember(): bool
    {
        return $this->membership !== null;
    }

    public function membership(): ?BusinessMember
    {
        return $this->membership;
    }

    public function acceptsChanges(): bool
    {
        return $this->business->acceptsChanges();
    }

    public function canUpdate(): bool
    {
        return $this->acceptsChanges() && $this->managesWholeBusiness();
    }

    public function canChangeStatus(): bool
    {
        return $this->acceptsChanges() && $this->isOwner();
    }

    public function canDelete(): bool
    {
        return $this->acceptsChanges() && $this->isOwner();
    }

    public function canCreateBranches(): bool
    {
        return $this->acceptsChanges() && $this->managesWholeBusiness();
    }

    public function canViewMembers(): bool
    {
        return $this->isOwner() || $this->hasRole(BusinessRole::Manager);
    }

    public function canManageMembers(): bool
    {
        return $this->acceptsChanges() && ($this->membership?->role->canManageMembers() ?? false);
    }

    /**
     * Business insights and peak times: owners and managers. A manager of
     * selected branches sees only those branches' data.
     */
    public function canViewInsights(): bool
    {
        return $this->isOwner() || $this->hasRole(BusinessRole::Manager);
    }

    public function canViewBranch(Branch $branch): bool
    {
        return $this->membership?->canAccessBranch($branch) ?? false;
    }

    public function canUpdateBranch(Branch $branch): bool
    {
        return $this->acceptsChanges()
            && ($this->membership?->role->canManageBusiness() ?? false)
            && $this->canViewBranch($branch);
    }

    public function canDeleteBranch(Branch $branch): bool
    {
        return $this->acceptsChanges() && $this->managesWholeBusiness() && $this->canViewBranch($branch);
    }

    public function canManageServices(Branch $branch): bool
    {
        return $this->canUpdateBranch($branch);
    }

    /**
     * Configure the branch's queues, open/close them, assign staff, rotate
     * public links: the owner, or a manager with access to the branch.
     */
    public function canManageQueues(Branch $branch): bool
    {
        return $this->canUpdateBranch($branch);
    }

    /**
     * Run a queue (sessions, calling and serving): queue managers, plus
     * employees assigned to this queue who still reach its branch.
     */
    public function canOperateQueue(Queue $queue): bool
    {
        if (! $this->acceptsChanges() || ! $this->canViewBranch($queue->branch)) {
            return false;
        }

        return $this->canManageQueues($queue->branch) || $this->isAssignedTo($queue);
    }

    public function isAssignedTo(Queue $queue): bool
    {
        return $this->membership !== null
            && QueueStaffAssignment::query()
                ->where('queue_id', $queue->getKey())
                ->where('business_member_id', $this->membership->getKey())
                ->whereNull('unassigned_at')
                ->exists();
    }

    /**
     * @return array<string, bool>
     */
    public function queuePermissions(Queue $queue): array
    {
        $manage = $this->canManageQueues($queue->branch);

        return [
            'manage' => $manage,
            'operate' => $manage || $this->canOperateQueue($queue),
        ];
    }

    /**
     * @return array<string, bool>
     */
    public function toArray(): array
    {
        return [
            'update' => $this->canUpdate(),
            'change_status' => $this->canChangeStatus(),
            'delete' => $this->canDelete(),
            'create_branches' => $this->canCreateBranches(),
            'view_members' => $this->canViewMembers(),
            'manage_members' => $this->canManageMembers(),
            'view_insights' => $this->canViewInsights(),
        ];
    }

    /**
     * @return array<string, bool>
     */
    public function branchPermissions(Branch $branch): array
    {
        return [
            'update' => $this->canUpdateBranch($branch),
            'delete' => $this->canDeleteBranch($branch),
            'manage_services' => $this->canManageServices($branch),
        ];
    }

    private function isOwner(): bool
    {
        return $this->membership?->isOwner() ?? false;
    }

    private function hasRole(BusinessRole $role): bool
    {
        return $this->membership?->role === $role;
    }

    /**
     * Owners, and managers whose access is not limited to selected branches.
     */
    private function managesWholeBusiness(): bool
    {
        return $this->isOwner()
            || ($this->hasRole(BusinessRole::Manager) && $this->membership->hasAllBranchAccess());
    }
}

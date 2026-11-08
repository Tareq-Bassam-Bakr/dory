<?php

namespace App\Services\Notifications;

use App\Models\BusinessMember;
use App\Models\Queue;
use App\Models\User;
use App\Services\Business\BusinessAbilities;
use Illuminate\Support\Collection;

/**
 * Who runs a queue, for staff notifications: the people who may operate it
 * (the owner, managers who reach its branch, employees assigned to it), with
 * an active membership and account. The same rule as QueuePolicy::operate.
 */
final class QueueStaffRecipients
{
    /**
     * Expects the queue with branch.business loaded.
     *
     * @return Collection<int, User>
     */
    public function for(Queue $queue): Collection
    {
        $business = $queue->branch->business;

        return BusinessMember::query()
            ->with('user')
            ->where('business_id', $business->getKey())
            ->get()
            ->filter(fn (BusinessMember $member): bool => $member->user !== null
                && $member->user->isActive()
                && BusinessAbilities::for($business, $member)->canOperateQueue($queue))
            ->map(fn (BusinessMember $member): User => $member->user)
            ->unique(fn (User $user): int => $user->getKey())
            ->values();
    }
}

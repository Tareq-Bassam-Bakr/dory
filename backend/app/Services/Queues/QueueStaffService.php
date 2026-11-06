<?php

namespace App\Services\Queues;

use App\Enums\BusinessRole;
use App\Models\BusinessMember;
use App\Models\Queue;
use App\Models\QueueStaffAssignment;
use App\Models\User;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Which team members operate a queue. Owners and managers with access to
 * the branch can always operate it; employees need an assignment.
 * Assignments are kept for history: removing one sets unassigned_at.
 */
final class QueueStaffService
{
    /**
     * Assigns the member; returns the existing assignment if there is one.
     *
     * @return array{0: QueueStaffAssignment, 1: bool} The assignment, and whether it is new.
     */
    public function assign(Queue $queue, BusinessMember $member, User $by): array
    {
        $this->ensureEligible($queue, $member);

        $existing = $this->activeAssignment($queue, $member);
        if ($existing !== null) {
            return [$existing, false];
        }

        try {
            $assignment = DB::transaction(function () use ($queue, $member, $by): QueueStaffAssignment {
                $assignment = new QueueStaffAssignment;
                $assignment->queue_id = $queue->getKey();
                $assignment->business_member_id = $member->getKey();
                $assignment->assigned_by = $by->getKey();
                $assignment->assigned_at = now();
                $assignment->saveOrFail();

                return $assignment;
            });
        } catch (UniqueConstraintViolationException) {
            // A concurrent request assigned the same member first.
            return [$this->activeAssignment($queue, $member) ?? throw new \LogicException('Assignment vanished.'), false];
        }

        return [$assignment->setRelation('member', $member), true];
    }

    public function unassign(QueueStaffAssignment $assignment, User $by): QueueStaffAssignment
    {
        if ($assignment->isActive()) {
            $assignment->unassigned_at = now();
            $assignment->unassigned_by = $by->getKey();
            $assignment->saveOrFail();
        }

        return $assignment;
    }

    /**
     * An active manager or employee of the queue's business who reaches its
     * branch. Owners operate every queue without an assignment.
     */
    private function ensureEligible(Queue $queue, BusinessMember $member): void
    {
        $eligible = $member->business_id === $queue->branch->business_id
            && $member->grantsAccess()
            && in_array($member->role, [BusinessRole::Manager, BusinessRole::Employee], true)
            && $member->canAccessBranch($queue->branch);

        if (! $eligible) {
            throw ValidationException::withMessages(['member' => __('api.queue.member_not_eligible')]);
        }
    }

    private function activeAssignment(Queue $queue, BusinessMember $member): ?QueueStaffAssignment
    {
        return QueueStaffAssignment::query()
            ->where('queue_id', $queue->getKey())
            ->where('business_member_id', $member->getKey())
            ->whereNull('unassigned_at')
            ->with('member.user')
            ->first();
    }
}

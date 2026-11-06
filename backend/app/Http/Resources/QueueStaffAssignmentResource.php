<?php

namespace App\Http\Resources;

use App\Models\QueueStaffAssignment;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Expects member.user and assigner.
 *
 * @mixin QueueStaffAssignment
 */
class QueueStaffAssignmentResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        /** @var QueueStaffAssignment $assignment */
        $assignment = $this->resource;
        $member = $assignment->member;

        return [
            'id' => $assignment->public_id,
            'member' => [
                'id' => $member->public_id,
                'name' => $member->user->name,
                'email' => $member->user->email,
                'role' => $member->role->value,
            ],
            'assigned_at' => $assignment->assigned_at->toIso8601String(),
            'assigned_by' => $assignment->assigner?->name,
            'unassigned_at' => $assignment->unassigned_at?->toIso8601String(),
            'active' => $assignment->isActive(),
        ];
    }
}

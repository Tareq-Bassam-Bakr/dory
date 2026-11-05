<?php

namespace App\Http\Resources;

use App\Enums\BranchAccess;
use App\Http\Resources\Concerns\ResolvesViewerAbilities;
use App\Models\Branch;
use App\Models\BusinessMember;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * A team member as seen by the business's owner and managers.
 *
 * @mixin BusinessMember
 */
class BusinessMemberResource extends JsonResource
{
    use ResolvesViewerAbilities;

    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        /** @var BusinessMember $member */
        $member = $this->resource;
        $isViewer = $member->user_id === $request->user()?->getKey();
        $changeable = ! $member->isOwner()
            && ! $isViewer
            && ($this->viewerAbilities($member->business_id)?->canManageMembers() ?? false);
        $access = $member->hasAllBranchAccess() ? BranchAccess::All : BranchAccess::Selected;

        return [
            'id' => $member->public_id,
            'user' => [
                'id' => $member->user->public_id,
                'name' => $member->user->name,
                'email' => $member->user->email,
            ],
            'role' => $member->role->value,
            'status' => $member->status->value,
            'branch_access' => $access->value,
            'branches' => $access === BranchAccess::Selected
                ? $member->branches->map(fn (Branch $branch) => ['id' => $branch->public_id, 'name' => $branch->name])->values()
                : [],
            'joined_at' => $member->joined_at?->toIso8601String(),
            'is_you' => $isViewer,
            'permissions' => ['update' => $changeable, 'remove' => $changeable],
        ];
    }
}

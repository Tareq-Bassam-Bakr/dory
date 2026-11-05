<?php

namespace App\Http\Resources;

use App\Enums\BranchAccess;
use App\Models\Branch;
use App\Models\BusinessInvitation;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * An invitation as seen by the business team. Never contains the token
 * (only its hash is stored, so it cannot be shown again).
 *
 * The `branches` relation is set by the controller from branch_ids.
 *
 * @mixin BusinessInvitation
 */
class BusinessInvitationResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        /** @var BusinessInvitation $invitation */
        $invitation = $this->resource;

        return [
            'id' => $invitation->public_id,
            'email' => $invitation->email,
            'role' => $invitation->role->value,
            'branch_access' => $invitation->branch_access->value,
            'branches' => $invitation->branch_access === BranchAccess::Selected && $invitation->relationLoaded('branches')
                ? $invitation->getRelation('branches')
                    ->map(fn (Branch $branch) => ['id' => $branch->public_id, 'name' => $branch->name])->values()
                : [],
            'status' => $invitation->effectiveStatus()->value,
            'expires_at' => $invitation->expires_at->toIso8601String(),
            'invited_by' => $invitation->relationLoaded('inviter') && $invitation->inviter !== null
                ? ['name' => $invitation->inviter->name]
                : null,
            'created_at' => $invitation->created_at?->toIso8601String(),
        ];
    }
}

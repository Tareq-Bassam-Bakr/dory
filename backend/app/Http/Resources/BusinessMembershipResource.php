<?php

namespace App\Http\Resources;

use App\Models\BusinessMember;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * A membership as seen by the member: which business, which role, which state.
 *
 * @mixin BusinessMember
 */
class BusinessMembershipResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'business' => new BusinessResource($this->whenLoaded('business')),
            'role' => $this->role->value,
            'status' => $this->status->value,
            'joined_at' => $this->joined_at?->toIso8601String(),
        ];
    }
}

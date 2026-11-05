<?php

namespace App\Http\Resources;

use App\Models\BusinessInvitation;
use App\Models\User;
use App\Services\Invitations\InvitationService;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * What the holder of an invitation link sees before accepting: which
 * business, which role, who invited them and until when.
 *
 * The invited email is masked, so a forwarded link reveals little.
 * `can_respond` is set only for a signed-in viewer.
 *
 * @mixin BusinessInvitation
 */
class InvitationPreviewResource extends JsonResource
{
    public function __construct(BusinessInvitation $invitation, private readonly ?User $viewer)
    {
        parent::__construct($invitation);
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        /** @var BusinessInvitation $invitation */
        $invitation = $this->resource;
        $business = $invitation->business;

        return [
            'business' => [
                'id' => $business->public_id,
                'name' => $business->name,
                'category' => $business->category !== null ? new BusinessCategoryResource($business->category) : null,
            ],
            'role' => $invitation->role->value,
            'email' => self::mask($invitation->email),
            'status' => $invitation->effectiveStatus()->value,
            'expires_at' => $invitation->expires_at->toIso8601String(),
            'invited_by' => $invitation->inviter !== null ? ['name' => $invitation->inviter->name] : null,
            'can_respond' => $this->viewer === null ? null : InvitationService::canRespond($invitation, $this->viewer),
        ];
    }

    /**
     * "sara.k@example.com" becomes "sa****@example.com".
     */
    private static function mask(string $email): string
    {
        [$local, $domain] = array_pad(explode('@', $email, 2), 2, '');

        return mb_substr($local, 0, min(2, mb_strlen($local))).'****@'.$domain;
    }
}

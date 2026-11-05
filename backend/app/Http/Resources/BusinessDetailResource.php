<?php

namespace App\Http\Resources;

use App\Models\Business;
use App\Models\BusinessMember;
use App\Services\Business\BusinessAbilities;
use App\Services\Business\BusinessProfileCompletion;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Storage;

/**
 * A business as seen by one of its members: full profile, statistics,
 * profile completion, the viewer's membership and permission hints.
 *
 * Build it with BusinessDetailResource::for(), which loads what it needs.
 *
 * @mixin Business
 */
class BusinessDetailResource extends JsonResource
{
    private const VIEWER = 'viewerMembership';

    /**
     * Prepares a single business for the resource: category, counts and the
     * viewer's membership.
     */
    public static function for(Business $business, ?BusinessMember $viewerMembership): self
    {
        $business->loadMissing('category');
        BusinessProfileCompletion::loadCounts($business);

        return new self(self::withViewer($business, $viewerMembership));
    }

    /**
     * Attaches the viewer's membership, used for `membership` and `permissions`.
     */
    public static function withViewer(Business $business, ?BusinessMember $membership): Business
    {
        return $business->setRelation(self::VIEWER, $membership);
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        /** @var Business $business */
        $business = $this->resource;
        $membership = $business->relationLoaded(self::VIEWER) ? $business->getRelation(self::VIEWER) : null;
        $hasCounts = array_key_exists('branches_count', $business->getAttributes());

        return [
            'id' => $business->public_id,
            'name' => $business->name,
            'slug' => $business->slug,
            'description' => $business->description,
            'category' => $business->relationLoaded('category') && $business->category !== null
                ? new BusinessCategoryResource($business->category)
                : null,
            'email' => $business->email,
            'phone' => $business->phone,
            'website' => $business->website,
            'timezone' => $business->timezone,
            'status' => $business->status->value,
            'logo_url' => self::publicUrl($business->logo_path),
            'cover_url' => self::publicUrl($business->cover_path),
            'stats' => $this->when($hasCounts, fn () => [
                'branches' => (int) $business->getAttribute('branches_count'),
                'services' => (int) $business->getAttribute('services_count'),
                'members' => (int) $business->getAttribute('members_count'),
            ]),
            'profile' => $this->when($hasCounts, fn () => BusinessProfileCompletion::for($business)->toArray()),
            'membership' => $membership instanceof BusinessMember ? [
                'id' => $membership->public_id,
                'role' => $membership->role->value,
                'branch_access' => $membership->hasAllBranchAccess() ? 'all' : 'selected',
            ] : null,
            'permissions' => BusinessAbilities::for($business, $membership)->toArray(),
            'created_at' => $business->created_at?->toIso8601String(),
            'updated_at' => $business->updated_at?->toIso8601String(),
        ];
    }

    private static function publicUrl(?string $path): ?string
    {
        return $path === null ? null : Storage::disk('public')->url($path);
    }
}

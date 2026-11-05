<?php

namespace App\Http\Resources;

use App\Http\Resources\Concerns\ResolvesViewerAbilities;
use App\Models\Branch;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * A branch. Coordinates are WGS 84 decimal degrees (OpenStreetMap).
 *
 * @mixin Branch
 */
class BranchResource extends JsonResource
{
    use ResolvesViewerAbilities;

    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        /** @var Branch $branch */
        $branch = $this->resource;
        $abilities = $this->viewerAbilities($branch->business_id);

        return [
            'id' => $branch->public_id,
            'business_id' => $this->whenLoaded('business', fn () => $branch->business->public_id),
            'name' => $branch->name,
            'address' => $branch->address,
            'country' => $branch->country,
            'city' => $branch->city,
            'region' => $branch->region,
            'latitude' => $branch->latitude,
            'longitude' => $branch->longitude,
            'phone' => $branch->phone,
            'email' => $branch->email,
            'timezone' => $branch->timezone,
            'status' => $branch->status->value,
            'services_count' => $this->whenCounted('services'),
            'permissions' => $abilities?->branchPermissions($branch),
            'created_at' => $branch->created_at?->toIso8601String(),
            'updated_at' => $branch->updated_at?->toIso8601String(),
        ];
    }
}

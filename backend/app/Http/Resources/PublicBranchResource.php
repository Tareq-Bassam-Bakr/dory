<?php

namespace App\Http\Resources;

use App\Models\Branch;
use App\Services\Discovery\DiscoveryStats;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * A branch as anyone may see it. No opening hours exist in the backend, so
 * none are implied: availability comes only from live queue data. Expects
 * business.category and the "discovery" relation (DiscoveryCards).
 *
 * @mixin Branch
 */
class PublicBranchResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        /** @var Branch $branch */
        $branch = $this->resource;
        /** @var DiscoveryStats $stats */
        $stats = $branch->getRelation('discovery');
        $business = $branch->business;

        return [
            'id' => $branch->public_id,
            'name' => $branch->name,
            'address' => $branch->address,
            'city' => $branch->city,
            'region' => $branch->region,
            'country' => $branch->country,
            'latitude' => $branch->latitude,
            'longitude' => $branch->longitude,
            'phone' => $branch->phone,
            'timezone' => $branch->timezone,
            'distance_km' => $stats->roundedDistance(),
            'services_count' => $stats->servicesCount,
            ...$stats->queueSummary(),
            'business' => [
                'id' => $business->public_id,
                'slug' => $business->slug,
                'name' => $business->name,
                'logo_url' => $business->logoUrl(),
                'category' => $business->category === null ? null : new BusinessCategoryResource($business->category),
            ],
        ];
    }
}

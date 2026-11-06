<?php

namespace App\Http\Resources;

use App\Models\Business;
use App\Services\Discovery\DiscoveryStats;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * A business as anyone may see it. Built field by field: no members, owner,
 * internal ids, status or audit data can appear here by accident. Expects
 * category and the "discovery" relation (DiscoveryCards).
 *
 * @mixin Business
 */
class PublicBusinessResource extends JsonResource
{
    private bool $detailed = false;

    /**
     * Adds the profile fields of the business page.
     */
    public function detailed(): static
    {
        $this->detailed = true;

        return $this;
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        /** @var Business $business */
        $business = $this->resource;
        /** @var DiscoveryStats $stats */
        $stats = $business->getRelation('discovery');

        return [
            'id' => $business->public_id,
            'slug' => $business->slug,
            'name' => $business->name,
            'category' => $business->category === null ? null : new BusinessCategoryResource($business->category),
            'logo_url' => $business->logoUrl(),
            'cover_url' => $business->coverUrl(),
            'branches_count' => $stats->branchesCount,
            'city' => $stats->city,
            'country' => $stats->country,
            'distance_km' => $stats->roundedDistance(),
            ...$stats->queueSummary(),
            'rating' => $this->rating($business),
            'is_favorite' => $stats->isFavorite,
            'description' => $this->when($this->detailed, $business->description),
            // The business's own public contact details, entered for customers.
            'email' => $this->when($this->detailed, $business->email),
            'phone' => $this->when($this->detailed, $business->phone),
            'website' => $this->when($this->detailed, $business->website),
        ];
    }

    /**
     * Shown only once enough real ratings exist.
     *
     * @return array{average: float, count: int}|null
     */
    private function rating(Business $business): ?array
    {
        if ($business->rating_average === null || $business->rating_count < (int) config('dory.discovery.min_ratings')) {
            return null;
        }

        return ['average' => $business->rating_average, 'count' => $business->rating_count];
    }
}

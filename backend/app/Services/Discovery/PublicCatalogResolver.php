<?php

namespace App\Services\Discovery;

use App\Exceptions\QueueException;
use App\Models\Branch;
use App\Models\Business;
use Illuminate\Database\Eloquent\Builder;

/**
 * Route resolution for public catalogue pages. Anything that is not public
 * is the same 404 as something that does not exist, so a suspended or
 * inactive business cannot be told apart from an unknown one.
 */
final class PublicCatalogResolver
{
    /**
     * By slug or public id.
     */
    public function business(string $value): Business
    {
        return Business::query()
            ->publiclyVisible()
            ->where(fn (Builder $query) => $query->where('slug', $value)->orWhere('public_id', $value))
            ->with('category')
            ->first()
            ?? throw QueueException::notFound('business');
    }

    public function branch(string $publicId): Branch
    {
        return Branch::query()
            ->publiclyVisible()
            ->where('public_id', $publicId)
            ->whereHas('business', fn (Builder $business) => $business->where('businesses.status', 'active'))
            ->with('business.category')
            ->first()
            ?? throw QueueException::notFound('branch');
    }

    /**
     * Any existing business, even one that is no longer public, so a customer
     * can always remove it from their favorites.
     */
    public function favoriteTarget(string $publicId): Business
    {
        return Business::withTrashed()->where('public_id', $publicId)->first()
            ?? throw QueueException::notFound('business');
    }
}

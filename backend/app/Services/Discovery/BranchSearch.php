<?php

namespace App\Services\Discovery;

use App\Models\Branch;
use App\Models\Business;
use App\Models\BusinessCategory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Pagination\LengthAwarePaginator;

/**
 * Public branch lists: nearest first around a point, or one business's
 * branches. Branches of businesses that are not public never appear.
 */
final class BranchSearch
{
    public function __construct(private readonly DiscoveryCards $cards) {}

    /**
     * @return LengthAwarePaginator<int, Branch>
     */
    public function nearby(DiscoveryFilters $filters, GeoCircle $circle, int $perPage, int $page): LengthAwarePaginator
    {
        $query = $this->visible()
            ->select('branches.*')
            ->selectRaw($circle->squaredDistanceSql().' as distance_sort');

        $circle->constrain($query);

        if ($filters->category !== null) {
            $id = BusinessCategory::query()->where('slug', $filters->category)->value('id');
            $query->whereHas('business', fn (Builder $business) => $business->where('category_id', $id));
        }
        if ($filters->accepting) {
            $query->whereHas('queues', fn (Builder $queues) => $queues->acceptingTickets());
        } elseif ($filters->openQueue) {
            $query->whereHas('queues', fn (Builder $queues) => $queues->openForJoining());
        }

        $paginator = $query
            ->orderBy('distance_sort')
            ->orderBy('branches.id')
            ->paginate($perPage, ['*'], 'page', $page);

        $this->cards->decorateBranches($paginator->getCollection(), $circle);

        return $paginator;
    }

    /**
     * @return LengthAwarePaginator<int, Branch>
     */
    public function ofBusiness(Business $business, ?GeoCircle $origin, int $perPage, int $page): LengthAwarePaginator
    {
        $query = $this->visible()->where('branches.business_id', $business->getKey())->select('branches.*');

        if ($origin !== null) {
            // Branches without coordinates come last.
            $query->selectRaw($origin->squaredDistanceSql().' as distance_sort')
                ->orderByRaw('case when branches.latitude is null or branches.longitude is null then 1 else 0 end')
                ->orderBy('distance_sort');
        }

        $paginator = $query->orderBy('branches.name')->orderBy('branches.id')->paginate($perPage, ['*'], 'page', $page);
        $this->cards->decorateBranches($paginator->getCollection(), $origin);

        return $paginator;
    }

    /**
     * @return Builder<Branch>
     */
    private function visible(): Builder
    {
        return Branch::query()
            ->publiclyVisible()
            ->whereHas('business', fn (Builder $business) => $business->where('businesses.status', 'active'))
            ->with('business.category');
    }
}

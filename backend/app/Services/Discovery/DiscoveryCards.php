<?php

namespace App\Services\Discovery;

use App\Models\Branch;
use App\Models\Business;
use App\Models\Queue;
use App\Models\Service;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Support\Collection;

/**
 * Adds the live numbers (distance, open queues, lowest wait, favorites) to a
 * page of businesses or branches, as a "discovery" relation. A fixed number
 * of queries per page, whatever its size.
 */
final class DiscoveryCards
{
    public function __construct(private readonly QueueAvailability $availability) {}

    /**
     * @param  Collection<int, Business>  $businesses
     */
    public function decorateBusinesses(Collection $businesses, ?GeoCircle $circle, ?User $viewer): void
    {
        if ($businesses->isEmpty()) {
            return;
        }

        $ids = $businesses->map(fn (Business $business) => $business->getKey());
        $branches = Branch::query()
            ->publiclyVisible()
            ->whereIn('business_id', $ids)
            ->orderBy('id')
            ->get(['id', 'business_id', 'city', 'country', 'latitude', 'longitude']);

        $queues = $this->openQueues($branches->modelKeys());
        $favorites = $viewer === null ? collect() : $viewer->favoriteBusinesses()
            ->whereIn('businesses.id', $ids)
            ->pluck('businesses.id')
            ->flip();

        foreach ($businesses as $business) {
            $own = $branches->where('business_id', $business->getKey())->values();
            $closest = $this->closest($own, $circle);
            $summary = $this->queueSummary($queues->whereIn('queue.branch_id', $own->modelKeys()));

            $business->setRelation('discovery', new DiscoveryStats(
                branchesCount: $own->count(),
                city: ($closest ?? $own->first())?->city,
                country: ($closest ?? $own->first())?->country,
                distanceKm: $closest === null ? null : $this->distance($closest, $circle),
                openQueues: $summary['open'],
                acceptingQueues: $summary['accepting'],
                lowestWaitMinutes: $summary['lowest'],
                isFavorite: $favorites->has($business->getKey()),
            ));
        }
    }

    /**
     * @param  Collection<int, Branch>  $branches
     */
    public function decorateBranches(Collection $branches, ?GeoCircle $circle): void
    {
        if ($branches->isEmpty()) {
            return;
        }

        $ids = $branches->map(fn (Branch $branch) => $branch->getKey())->all();
        $queues = $this->openQueues($ids);
        $services = Service::query()
            ->publiclyVisible()
            ->whereIn('branch_id', $ids)
            ->groupBy('branch_id')
            ->selectRaw('branch_id, count(*) as total')
            ->pluck('total', 'branch_id');

        foreach ($branches as $branch) {
            $summary = $this->queueSummary($queues->where('queue.branch_id', $branch->getKey()));

            $branch->setRelation('discovery', new DiscoveryStats(
                servicesCount: (int) ($services[$branch->getKey()] ?? 0),
                distanceKm: $circle === null ? null : $this->distance($branch, $circle),
                openQueues: $summary['open'],
                acceptingQueues: $summary['accepting'],
                lowestWaitMinutes: $summary['lowest'],
            ));
        }
    }

    /**
     * Queues with an open session in these branches, with availability.
     *
     * @param  list<int>  $branchIds
     * @return Collection<int, QueueAvailabilityView>
     */
    private function openQueues(array $branchIds): Collection
    {
        if ($branchIds === []) {
            return collect();
        }

        /** @var EloquentCollection<int, Queue> $queues */
        $queues = Queue::query()
            ->openForJoining()
            ->whereIn('branch_id', $branchIds)
            ->with(['activeSession', 'branch.business', 'service'])
            ->get();

        return collect($this->availability->for($queues))
            ->filter(fn (QueueAvailabilityView $view) => $view->status === 'open')
            ->values();
    }

    /**
     * @param  Collection<int, QueueAvailabilityView>  $views
     * @return array{open: int, accepting: int, lowest: int|null}
     */
    private function queueSummary(Collection $views): array
    {
        $accepting = $views->filter(fn (QueueAvailabilityView $view) => $view->acceptingJoins);

        return [
            'open' => $views->count(),
            'accepting' => $accepting->count(),
            'lowest' => $accepting->map(fn (QueueAvailabilityView $view) => $view->estimate->minutes())->min(),
        ];
    }

    /**
     * @param  Collection<int, Branch>  $branches
     */
    private function closest(Collection $branches, ?GeoCircle $circle): ?Branch
    {
        if ($circle === null) {
            return null;
        }

        return $branches
            ->filter(fn (Branch $branch) => $branch->latitude !== null && $branch->longitude !== null)
            ->sortBy(fn (Branch $branch) => $this->distance($branch, $circle))
            ->first();
    }

    private function distance(Branch $branch, ?GeoCircle $circle): ?float
    {
        return $circle === null || $branch->latitude === null || $branch->longitude === null
            ? null
            : $circle->distanceTo($branch->latitude, $branch->longitude);
    }
}

<?php

namespace App\Services\Discovery;

use App\Models\BusinessCategory;
use App\Models\Queue;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Pagination\LengthAwarePaginator;

/**
 * Queues a customer could join right now, for the home screen. Candidates
 * are bounded; estimated waits are compared in PHP (see BusinessSearch).
 */
final class AvailableQueues
{
    public function __construct(private readonly QueueAvailability $availability) {}

    /**
     * @return LengthAwarePaginator<int, QueueAvailabilityView>
     */
    public function paginate(DiscoveryFilters $filters, int $perPage, int $page): LengthAwarePaginator
    {
        $queues = Queue::query()
            ->acceptingTickets()
            ->whereHas('branch', function (Builder $branch) use ($filters): void {
                $branch->publiclyVisible()->whereHas('business', fn (Builder $business) => $business->where('businesses.status', 'active'));
                if ($filters->country !== null) {
                    $branch->where('branches.country', $filters->country);
                }
                if ($filters->city !== null) {
                    $branch->where('branches.city', $filters->city);
                }
                $filters->circle?->constrain($branch);
                if ($filters->category !== null) {
                    $id = BusinessCategory::query()->where('slug', $filters->category)->value('id');
                    $branch->whereHas('business', fn (Builder $business) => $business->where('category_id', $id));
                }
            })
            ->with(['activeSession', 'branch.business.category', 'service'])
            ->orderBy('queues.id')
            ->limit((int) config('dory.discovery.wait_candidates'))
            ->get();

        $circle = $filters->circle;
        $byDistance = $filters->sort === DiscoveryFilters::SORT_NEAREST && $circle !== null;

        $views = collect($this->availability->for($queues))
            ->filter(fn (QueueAvailabilityView $view) => $view->acceptingJoins)
            ->filter(fn (QueueAvailabilityView $view) => $filters->maxWaitMinutes === null || $view->estimate->minutes() <= $filters->maxWaitMinutes)
            ->sortBy(fn (QueueAvailabilityView $view) => $byDistance
                ? [$this->distance($view, $circle), $view->estimate->seconds]
                : [$view->estimate->seconds, $view->queue->id])
            ->values();

        return new LengthAwarePaginator($views->forPage($page, $perPage)->values(), $views->count(), $perPage, $page);
    }

    private function distance(QueueAvailabilityView $view, GeoCircle $circle): float
    {
        $branch = $view->queue->branch;

        return $branch->latitude === null || $branch->longitude === null
            ? PHP_FLOAT_MAX
            : $circle->distanceTo($branch->latitude, $branch->longitude);
    }
}

<?php

namespace App\Services\Discovery;

use App\Models\Branch;
use App\Models\Business;
use App\Models\BusinessCategory;
use App\Models\Queue;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;

/**
 * Server-side business search (docs/SEARCH_AND_FILTERS.md).
 *
 * Only publicly visible businesses are ever considered. Filtering and
 * sorting happen in SQL, except estimated waits: those need the wait
 * estimator, so a bounded set of candidates is compared in PHP.
 */
final class BusinessSearch
{
    public function __construct(
        private readonly DiscoveryCards $cards,
        private readonly QueueAvailability $availability,
    ) {}

    /**
     * @return LengthAwarePaginator<int, Business>
     */
    public function paginate(DiscoveryFilters $filters, int $perPage, int $page, ?User $viewer): LengthAwarePaginator
    {
        $query = $this->query($filters);

        if ($filters->needsWaitEstimates()) {
            return $this->paginateByWait($query, $filters, $perPage, $page, $viewer);
        }

        $this->sort($query, $filters);
        $paginator = $query->paginate($perPage, ['*'], 'page', $page);
        $this->cards->decorateBusinesses($paginator->getCollection(), $filters->circle, $viewer);

        return $paginator;
    }

    /**
     * @return Builder<Business>
     */
    private function query(DiscoveryFilters $filters): Builder
    {
        $query = Business::query()->publiclyVisible()->select('businesses.*')->with('category');

        $query->whereHas('branches', fn (Builder $branches) => $this->constrainBranches($branches, $filters));

        if ($filters->category !== null) {
            $query->whereIn('businesses.category_id', $this->categoryIdsBySlug($filters->category));
        }
        if ($filters->minRating !== null) {
            $query->where('businesses.rating_count', '>=', (int) config('dory.discovery.min_ratings'))
                ->where('businesses.rating_average', '>=', $filters->minRating);
        }
        if ($filters->q !== null) {
            $this->search($query, $filters->q);
        }

        return $query;
    }

    /**
     * @param  Builder<Branch>  $branches
     */
    private function constrainBranches(Builder $branches, DiscoveryFilters $filters): void
    {
        $branches->publiclyVisible();

        if ($filters->country !== null) {
            $branches->where('branches.country', $filters->country);
        }
        if ($filters->city !== null) {
            $branches->where('branches.city', $filters->city);
        }
        $filters->circle?->constrain($branches);

        if ($filters->service !== null) {
            $branches->whereHas('services', function (Builder $services) use ($filters): void {
                $services->publiclyVisible()
                    ->whereRaw('services.name'.SearchTerm::LIKE, [SearchTerm::contains($filters->service)]);
            });
        }
        // Waits only exist for queues a customer could join.
        if ($filters->accepting || $filters->needsWaitEstimates()) {
            $branches->whereHas('queues', fn (Builder $queues) => $queues->acceptingTickets());
        } elseif ($filters->openQueue) {
            $branches->whereHas('queues', fn (Builder $queues) => $queues->openForJoining());
        }
    }

    /**
     * Matches the business name, its category (either language), or any of
     * its public branches and services.
     *
     * @param  Builder<Business>  $query
     */
    private function search(Builder $query, string $term): void
    {
        $pattern = SearchTerm::contains($term);
        $categories = $this->categoryIdsMatching($term);

        $query->where(function (Builder $match) use ($term, $pattern, $categories): void {
            $match->whereRaw('businesses.name'.SearchTerm::LIKE, [$pattern])
                ->orWhereIn('businesses.category_id', $categories)
                ->orWhereHas('branches', function (Builder $branches) use ($term, $pattern): void {
                    $branches->publiclyVisible()->where(function (Builder $place) use ($term, $pattern): void {
                        foreach (['name', 'city', 'region', 'address'] as $column) {
                            $place->orWhereRaw("branches.{$column}".SearchTerm::LIKE, [$pattern]);
                        }
                        if (strlen($term) === 2) {
                            $place->orWhere('branches.country', strtoupper($term));
                        }
                    });
                })
                ->orWhereHas('branches', function (Builder $branches) use ($pattern): void {
                    $branches->publiclyVisible()->whereHas('services', function (Builder $services) use ($pattern): void {
                        $services->publiclyVisible()->whereRaw('services.name'.SearchTerm::LIKE, [$pattern]);
                    });
                });
        });
    }

    /**
     * @param  Builder<Business>  $query
     */
    private function sort(Builder $query, DiscoveryFilters $filters): void
    {
        match (true) {
            $filters->sort === DiscoveryFilters::SORT_NEAREST && $filters->circle !== null => $this->sortByDistance($query, $filters->circle),
            $filters->sort === DiscoveryFilters::SORT_RATING => $this->sortByRating($query),
            default => $this->sortByRelevance($query, $filters->q),
        };

        $query->orderBy('businesses.name')->orderBy('businesses.id');
    }

    /**
     * Name matches first; businesses a customer can use right now next.
     *
     * @param  Builder<Business>  $query
     */
    private function sortByRelevance(Builder $query, ?string $term): void
    {
        if ($term !== null) {
            $query->orderByRaw(
                "case when businesses.name = ? then 0 when businesses.name like ? escape '!' then 1 when businesses.name like ? escape '!' then 2 else 3 end",
                [$term, SearchTerm::prefix($term), SearchTerm::contains($term)],
            );
        }

        $query->addSelect(['open_queues_sort' => $this->openQueueCount()])
            ->orderByRaw('case when open_queues_sort > 0 then 0 else 1 end');
        $this->sortByRating($query);
    }

    /**
     * Businesses with too few real ratings come after those with enough.
     *
     * @param  Builder<Business>  $query
     */
    private function sortByRating(Builder $query): void
    {
        $query->orderByRaw('case when businesses.rating_count >= ? then 0 else 1 end', [(int) config('dory.discovery.min_ratings')])
            ->orderByDesc('businesses.rating_average')
            ->orderByDesc('businesses.rating_count');
    }

    /**
     * @param  Builder<Business>  $query
     */
    private function sortByDistance(Builder $query, GeoCircle $circle): void
    {
        $nearest = Branch::query()
            ->publiclyVisible()
            ->selectRaw('min('.$circle->squaredDistanceSql().')')
            ->whereColumn('branches.business_id', 'businesses.id');

        $query->addSelect(['nearest_sort' => $nearest])->orderBy('nearest_sort');
    }

    /**
     * @return Builder<Queue>
     */
    private function openQueueCount(): Builder
    {
        return Queue::query()
            ->selectRaw('count(*)')
            ->join('branches as queue_branches', 'queue_branches.id', '=', 'queues.branch_id')
            ->whereColumn('queue_branches.business_id', 'businesses.id')
            ->where('queue_branches.status', 'active')
            ->whereNull('queue_branches.deleted_at')
            ->openForJoining();
    }

    /**
     * Estimated waits are computed per queue, so the first candidates are
     * compared in PHP and the sorted result is paged by hand. Only businesses
     * with a queue accepting tickets are listed (a closed queue has no wait).
     *
     * @param  Builder<Business>  $query
     * @return LengthAwarePaginator<int, Business>
     */
    private function paginateByWait(Builder $query, DiscoveryFilters $filters, int $perPage, int $page, ?User $viewer): LengthAwarePaginator
    {
        /** @var Collection<int, Business> $candidates */
        $candidates = $query
            ->orderBy('businesses.name')
            ->orderBy('businesses.id')
            ->limit((int) config('dory.discovery.wait_candidates'))
            ->get();

        $this->cards->decorateBusinesses($candidates, $filters->circle, $viewer);

        $matching = $candidates
            ->filter(function (Business $business) use ($filters): bool {
                $wait = $this->stats($business)->lowestWaitMinutes;

                return $wait !== null && ($filters->maxWaitMinutes === null || $wait <= $filters->maxWaitMinutes);
            })
            ->sortBy(fn (Business $business) => [$this->stats($business)->lowestWaitMinutes, $business->name])
            ->values();

        return new LengthAwarePaginator(
            $matching->forPage($page, $perPage)->values(),
            $matching->count(),
            $perPage,
            $page,
        );
    }

    private function stats(Business $business): DiscoveryStats
    {
        /** @var DiscoveryStats */
        return $business->getRelation('discovery');
    }

    /**
     * The category and its subcategories.
     *
     * @return list<int>
     */
    private function categoryIdsBySlug(string $slug): array
    {
        $id = BusinessCategory::query()->where('slug', $slug)->value('id');

        return $id === null ? [] : BusinessCategory::query()
            ->whereKey($id)
            ->orWhere('parent_id', $id)
            ->pluck('id')
            ->all();
    }

    /**
     * Categories are few, and their names are JSON whose stored form differs
     * per engine, so they are matched here rather than in SQL.
     *
     * @return list<int>
     */
    private function categoryIdsMatching(string $term): array
    {
        return BusinessCategory::query()
            ->get(['id', 'name'])
            ->filter(fn (BusinessCategory $category): bool => collect($category->name)
                ->contains(fn (mixed $name): bool => is_string($name) && mb_stripos($name, $term) !== false))
            ->modelKeys();
    }
}

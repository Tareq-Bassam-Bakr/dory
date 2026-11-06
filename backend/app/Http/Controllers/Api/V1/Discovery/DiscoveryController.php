<?php

namespace App\Http\Controllers\Api\V1\Discovery;

use App\Http\Controllers\Controller;
use App\Http\Requests\Discovery\DiscoveryRequest;
use App\Http\Requests\Discovery\NearbyRequest;
use App\Http\Resources\BusinessCategoryResource;
use App\Http\Resources\PublicBranchResource;
use App\Http\Resources\PublicBusinessResource;
use App\Models\Branch;
use App\Models\Business;
use App\Models\Queue;
use App\Models\Service;
use App\Models\User;
use App\Services\Discovery\AvailableQueues;
use App\Services\Discovery\BranchSearch;
use App\Services\Discovery\BusinessSearch;
use App\Services\Discovery\DiscoveryCards;
use App\Services\Discovery\QueueAvailability;
use App\Services\Discovery\QueueAvailabilityView;
use App\Services\Discovery\SearchTerm;
use App\Support\ApiResponse;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;

/**
 * Public browsing for customers and guests (docs/BUSINESS_DISCOVERY.md).
 *
 * No route here requires an account. A valid bearer token is only used to
 * mark the viewer's own favorites.
 */
class DiscoveryController extends Controller
{
    public function __construct(
        private readonly BusinessSearch $businessSearch,
        private readonly BranchSearch $branchSearch,
        private readonly AvailableQueues $availableQueues,
        private readonly DiscoveryCards $cards,
        private readonly QueueAvailability $availability,
    ) {}

    public function businesses(DiscoveryRequest $request): JsonResponse
    {
        return ApiResponse::paginated(
            $this->businessSearch->paginate($request->filters(), $request->perPage(), $request->page(), $this->viewer()),
            PublicBusinessResource::class,
        );
    }

    public function business(DiscoveryRequest $request, Business $publicBusiness): JsonResponse
    {
        $this->cards->decorateBusinesses(collect([$publicBusiness]), $request->circle(), $this->viewer());

        return ApiResponse::success((new PublicBusinessResource($publicBusiness))->detailed());
    }

    public function businessBranches(DiscoveryRequest $request, Business $publicBusiness): JsonResponse
    {
        return ApiResponse::paginated(
            $this->branchSearch->ofBusiness($publicBusiness, $request->circle(), $request->perPage(), $request->page()),
            PublicBranchResource::class,
        );
    }

    /**
     * Branches around a location, nearest first. Feeds the map and the
     * "nearby" section.
     */
    public function nearby(NearbyRequest $request): JsonResponse
    {
        return ApiResponse::paginated(
            $this->branchSearch->nearby($request->filters(), $request->circle(), $request->perPage(), $request->page()),
            PublicBranchResource::class,
        );
    }

    /**
     * Queues accepting tickets right now, lowest estimated wait first.
     */
    public function queues(DiscoveryRequest $request): JsonResponse
    {
        $page = $this->availableQueues->paginate($request->filters(), $request->perPage(), $request->page());
        $circle = $request->circle();

        $items = collect($page->items())->map(function (QueueAvailabilityView $view) use ($circle): array {
            $branch = $view->queue->branch;
            $business = $branch->business;
            $located = $circle !== null && $branch->latitude !== null && $branch->longitude !== null;

            return [
                ...$view->toArray(),
                'service' => ['id' => $view->queue->service->public_id, 'name' => $view->queue->service->name],
                'branch' => [
                    'id' => $branch->public_id,
                    'name' => $branch->name,
                    'city' => $branch->city,
                    'distance_km' => $located ? round($circle->distanceTo($branch->latitude, $branch->longitude), 2) : null,
                ],
                'business' => [
                    'id' => $business->public_id,
                    'slug' => $business->slug,
                    'name' => $business->name,
                    'logo_url' => $business->logoUrl(),
                    'category' => $business->category === null ? null : new BusinessCategoryResource($business->category),
                ],
            ];
        });

        return $this->paginatedArray($page, $items->all());
    }

    /**
     * Cities with public branches, for choosing a location by hand. The
     * coordinates are the average of those branches: no geocoding service
     * is involved.
     */
    public function cities(DiscoveryRequest $request): JsonResponse
    {
        $term = $request->filters()->q;

        $page = Branch::query()
            ->publiclyVisible()
            ->whereHas('business', fn (Builder $business) => $business->where('businesses.status', 'active'))
            ->whereNotNull('branches.city')
            ->when($term !== null, fn (Builder $query) => $query->whereRaw('branches.city'.SearchTerm::LIKE, [SearchTerm::contains((string) $term)]))
            ->groupBy('branches.country', 'branches.city')
            ->selectRaw('branches.country, branches.city, count(*) as branches_count, avg(branches.latitude) as latitude, avg(branches.longitude) as longitude')
            ->orderByDesc('branches_count')
            ->orderBy('branches.city')
            ->toBase()
            ->paginate($request->perPage(), ['*'], 'page', $request->page());

        return $this->paginatedArray($page, collect($page->items())->map(fn (object $city): array => [
            'city' => $city->city,
            'country' => $city->country,
            'branches_count' => (int) $city->branches_count,
            'latitude' => $city->latitude === null ? null : round((float) $city->latitude, 7),
            'longitude' => $city->longitude === null ? null : round((float) $city->longitude, 7),
        ])->all());
    }

    public function branch(DiscoveryRequest $request, Branch $publicBranch): JsonResponse
    {
        $this->cards->decorateBranches(collect([$publicBranch]), $request->circle());

        return ApiResponse::success(new PublicBranchResource($publicBranch));
    }

    /**
     * Active services of a branch, each with its live queue, if it has one.
     */
    public function branchServices(DiscoveryRequest $request, Branch $publicBranch): JsonResponse
    {
        $page = Service::query()
            ->publiclyVisible()
            ->where('branch_id', $publicBranch->getKey())
            ->with(['queues.activeSession', 'queues.service', 'queues.branch.business'])
            ->orderBy('display_order')
            ->orderBy('name')
            ->orderBy('id')
            ->paginate($request->perPage(), ['*'], 'page', $request->page());

        $queues = $page->getCollection()->flatMap(fn (Service $service) => $service->queues)->values();
        $views = $this->availability->for($queues);

        return $this->paginatedArray($page, $page->getCollection()->map(function (Service $service) use ($views): array {
            /** @var Queue|null $queue */
            $queue = $service->queues->first();

            return [
                'id' => $service->public_id,
                'name' => $service->name,
                'description' => $service->description,
                'estimated_duration_minutes' => $service->estimated_duration_minutes,
                'queue' => $queue === null ? null : $views[$queue->getKey()]->toArray(),
            ];
        })->all());
    }

    /**
     * The signed-in viewer, if a valid token was sent. Never required.
     */
    private function viewer(): ?User
    {
        $user = Auth::guard('sanctum')->user();

        return $user instanceof User && $user->isActive() ? $user : null;
    }

    /**
     * @param  LengthAwarePaginator<int, mixed>  $page
     * @param  list<array<string, mixed>>  $items
     */
    private function paginatedArray(LengthAwarePaginator $page, array $items): JsonResponse
    {
        return ApiResponse::success($items, meta: [
            'pagination' => [
                'current_page' => $page->currentPage(),
                'per_page' => $page->perPage(),
                'total' => $page->total(),
                'last_page' => $page->lastPage(),
            ],
        ]);
    }
}

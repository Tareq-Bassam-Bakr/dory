<?php

namespace App\Http\Controllers\Api\V1\Customers;

use App\Exceptions\QueueException;
use App\Http\Controllers\Controller;
use App\Http\Requests\Customers\StoreFavoriteRequest;
use App\Http\Resources\PublicBusinessResource;
use App\Models\Business;
use App\Services\Discovery\DiscoveryCards;
use App\Support\ApiResponse;
use App\Support\Pagination;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * Businesses the signed-in customer saved (docs/CUSTOMER_FAVORITES.md).
 * Every query goes through the caller's own id; there is no way to name
 * another user. A saved business that stops being public is hidden from the
 * list, and comes back if it becomes public again.
 */
class FavoriteController extends Controller
{
    public function __construct(private readonly DiscoveryCards $cards) {}

    public function index(Request $request): JsonResponse
    {
        $user = $request->user();

        $paginator = $user->favoriteBusinesses()
            ->publiclyVisible()
            ->with('category')
            ->orderByDesc('customer_favorites.id')
            ->paginate(Pagination::perPage($request));

        $this->cards->decorateBusinesses($paginator->getCollection(), null, $user);

        return ApiResponse::paginated($paginator, PublicBusinessResource::class);
    }

    /**
     * Saving twice is not an error. The unique index decides under concurrency.
     */
    public function store(StoreFavoriteRequest $request): JsonResponse
    {
        $user = $request->user();

        $business = Business::query()
            ->publiclyVisible()
            ->where('public_id', $request->validated('business'))
            ->with('category')
            ->first() ?? throw QueueException::notFound('business');

        $added = DB::table('customer_favorites')->insertOrIgnore([
            'user_id' => $user->getKey(),
            'business_id' => $business->getKey(),
            'created_at' => now(),
        ]);

        $this->cards->decorateBusinesses(collect([$business]), null, $user);
        $resource = new PublicBusinessResource($business);

        return $added > 0
            ? ApiResponse::created($resource, __('api.favorites.saved'))
            : ApiResponse::success($resource, __('api.favorites.already_saved'));
    }

    public function destroy(Request $request, Business $favoriteBusiness): JsonResponse
    {
        $request->user()->favoriteBusinesses()->detach($favoriteBusiness->getKey());

        return ApiResponse::success(message: __('api.favorites.removed'));
    }
}

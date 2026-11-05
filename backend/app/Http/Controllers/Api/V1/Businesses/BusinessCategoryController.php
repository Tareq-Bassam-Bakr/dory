<?php

namespace App\Http\Controllers\Api\V1\Businesses;

use App\Http\Controllers\Controller;
use App\Http\Resources\BusinessCategoryResource;
use App\Models\BusinessCategory;
use App\Support\ApiResponse;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Http\JsonResponse;

/**
 * GET /business-categories: public reference data. Active top-level
 * categories in display order, each with its active subcategories.
 *
 * The list is small and bounded, so it is not paginated.
 */
class BusinessCategoryController extends Controller
{
    public function __invoke(): JsonResponse
    {
        $categories = BusinessCategory::query()
            ->active()
            ->whereNull('parent_id')
            ->ordered()
            ->with(['children' => fn (HasMany $children) => $children->active()->ordered()])
            ->get();

        return ApiResponse::success(BusinessCategoryResource::collection($categories));
    }
}

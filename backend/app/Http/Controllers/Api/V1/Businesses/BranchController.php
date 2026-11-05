<?php

namespace App\Http\Controllers\Api\V1\Businesses;

use App\Enums\BranchStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Branches\StoreBranchRequest;
use App\Http\Requests\Branches\UpdateBranchRequest;
use App\Http\Resources\BranchResource;
use App\Models\Branch;
use App\Models\Business;
use App\Services\Business\BranchService;
use App\Services\Business\CurrentBusinessContext;
use App\Support\ApiResponse;
use App\Support\Pagination;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

/**
 * Branch management. {business} and {branch} are resolved through the
 * user's memberships and branch access (TenantResolver): branches the user
 * may not reach return 404 before these methods run.
 */
class BranchController extends Controller
{
    public function __construct(
        private readonly BranchService $branches,
        private readonly CurrentBusinessContext $context,
    ) {}

    /**
     * GET /businesses/{business}/branches: the branches the user may reach.
     * Optional filter: ?status=active|inactive.
     */
    public function index(Request $request, Business $business): JsonResponse
    {
        Gate::authorize('view', $business);
        $filters = $request->validate(['status' => ['sometimes', Rule::enum(BranchStatus::class)]]);

        $membership = $this->context->membership();

        $paginator = $business->branches()
            ->withCount('services')
            ->when(! $membership->hasAllBranchAccess(), fn ($query) => $query
                ->whereIn('id', $membership->branches()->pluck('branches.id')))
            ->when(isset($filters['status']), fn ($query) => $query->where('status', $filters['status']))
            ->orderBy('name')
            ->orderBy('id')
            ->paginate(Pagination::perPage($request));

        $paginator->getCollection()->each(fn (Branch $branch) => $branch->setRelation('business', $business));

        return ApiResponse::paginated($paginator, BranchResource::class);
    }

    public function store(StoreBranchRequest $request, Business $business): JsonResponse
    {
        $branch = $this->branches->create($business, $request->validated());

        return ApiResponse::created($this->present($branch, $business), __('api.branch.created'));
    }

    public function show(Branch $branch): JsonResponse
    {
        Gate::authorize('view', $branch);

        return ApiResponse::success($this->present($branch, $branch->business));
    }

    public function update(UpdateBranchRequest $request, Branch $branch): JsonResponse
    {
        $this->branches->update($branch, $request->validated());

        return ApiResponse::success($this->present($branch, $branch->business), __('api.branch.updated'));
    }

    /**
     * DELETE /branches/{branch}: soft-deletes the branch and archives its services.
     */
    public function destroy(Branch $branch): JsonResponse
    {
        Gate::authorize('delete', $branch);

        $this->branches->delete($branch);

        return ApiResponse::success(message: __('api.branch.deleted'));
    }

    private function present(Branch $branch, Business $business): BranchResource
    {
        $branch->setRelation('business', $business)->loadCount('services');

        return new BranchResource($branch);
    }
}

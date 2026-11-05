<?php

namespace App\Http\Controllers\Api\V1\Businesses;

use App\Http\Controllers\Controller;
use App\Http\Requests\Businesses\StoreBusinessRequest;
use App\Http\Requests\Businesses\UpdateBusinessRequest;
use App\Http\Resources\BusinessDetailResource;
use App\Models\Business;
use App\Services\Business\BusinessProfileCompletion;
use App\Services\Business\BusinessService;
use App\Services\Business\CurrentBusinessContext;
use App\Support\ApiResponse;
use App\Support\Pagination;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

/**
 * Business management. {business} is resolved through the user's own
 * active memberships (TenantResolver), so non-members get 404 before any
 * of these methods runs; the policies then check the role.
 */
class BusinessController extends Controller
{
    public function __construct(
        private readonly BusinessService $businesses,
        private readonly CurrentBusinessContext $context,
    ) {}

    /**
     * GET /businesses/manage: businesses where the user is an active member.
     */
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();

        $paginator = BusinessProfileCompletion::withCounts(Business::query()->accessibleBy($user))
            ->with([
                'category',
                'members' => fn (HasMany $members) => $members->where('user_id', $user->getKey())->active(),
            ])
            ->orderBy('name')
            ->orderBy('id')
            ->paginate(Pagination::perPage($request));

        $paginator->getCollection()->each(
            fn (Business $business) => BusinessDetailResource::withViewer($business, $business->members->first()),
        );

        return ApiResponse::paginated($paginator, BusinessDetailResource::class);
    }

    /**
     * POST /businesses: the creator becomes the owner in the same transaction.
     */
    public function store(StoreBusinessRequest $request): JsonResponse
    {
        $user = $request->user();

        [$business, $created] = $this->businesses->create(
            $user,
            $request->category(),
            $request->businessAttributes(),
            $request->branchAttributes(),
            $request->serviceAttributes(),
            $request->idempotencyKey(),
        );

        $resource = BusinessDetailResource::for($business, $this->context->membershipFor($user, $business));

        return $created
            ? ApiResponse::created($resource, __('api.business.created'))
            : ApiResponse::success($resource, __('api.business.already_created'), meta: ['idempotent_replay' => true]);
    }

    public function show(Business $business): JsonResponse
    {
        Gate::authorize('view', $business);

        return ApiResponse::success(BusinessDetailResource::for($business, $this->context->membership()));
    }

    public function update(UpdateBusinessRequest $request, Business $business): JsonResponse
    {
        $this->businesses->update(
            $business,
            $request->user(),
            $request->businessAttributes(),
            $request->category(),
            $request->status(),
        );

        return ApiResponse::success(
            BusinessDetailResource::for($business, $this->context->membership()),
            __('api.business.updated'),
        );
    }

    /**
     * DELETE /businesses/{business}: owner only; archives the business.
     */
    public function destroy(Request $request, Business $business): JsonResponse
    {
        Gate::authorize('delete', $business);

        $this->businesses->delete($business, $request->user());

        return ApiResponse::success(message: __('api.business.deleted'));
    }
}

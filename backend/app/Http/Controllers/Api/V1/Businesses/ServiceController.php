<?php

namespace App\Http\Controllers\Api\V1\Businesses;

use App\Enums\ServiceStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Services\StoreServiceRequest;
use App\Http\Requests\Services\UpdateServiceRequest;
use App\Http\Resources\ServiceResource;
use App\Models\Branch;
use App\Models\Service;
use App\Services\Catalog\ServiceCatalogService;
use App\Support\ApiResponse;
use App\Support\Pagination;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

/**
 * Services of a branch. {branch} and {service} are resolved through the
 * user's memberships and branch access (TenantResolver), so a service is
 * only ever reached through a branch the user may access.
 */
class ServiceController extends Controller
{
    public function __construct(private readonly ServiceCatalogService $services) {}

    /**
     * GET /branches/{branch}/services, in display order.
     * Optional filter: ?status=active|inactive.
     */
    public function index(Request $request, Branch $branch): JsonResponse
    {
        Gate::authorize('view', $branch);
        $filters = $request->validate(['status' => ['sometimes', Rule::enum(ServiceStatus::class)]]);

        $paginator = $branch->services()
            ->when(isset($filters['status']), fn ($query) => $query->where('status', $filters['status']))
            ->orderBy('display_order')
            ->orderBy('name')
            ->orderBy('id')
            ->paginate(Pagination::perPage($request));

        $paginator->getCollection()->each(fn (Service $service) => $service->setRelation('branch', $branch));

        return ApiResponse::paginated($paginator, ServiceResource::class);
    }

    public function store(StoreServiceRequest $request, Branch $branch): JsonResponse
    {
        $service = $this->services->create($branch, $request->validated());

        return ApiResponse::created(new ServiceResource($service->setRelation('branch', $branch)), __('api.service.created'));
    }

    public function show(Service $service): JsonResponse
    {
        Gate::authorize('view', $service);

        return ApiResponse::success(new ServiceResource($service));
    }

    public function update(UpdateServiceRequest $request, Service $service): JsonResponse
    {
        $this->services->update($service, $request->validated());

        return ApiResponse::success(new ServiceResource($service), __('api.service.updated'));
    }

    /**
     * DELETE /services/{service}: archives the service (soft delete).
     */
    public function destroy(Service $service): JsonResponse
    {
        Gate::authorize('delete', $service);

        $this->services->archive($service);

        return ApiResponse::success(message: __('api.service.archived'));
    }
}

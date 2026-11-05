<?php

namespace App\Http\Controllers\Api\V1\Businesses;

use App\Http\Controllers\Controller;
use App\Http\Requests\Members\UpdateMemberRequest;
use App\Http\Resources\BusinessMemberResource;
use App\Models\Business;
use App\Models\BusinessMember;
use App\Services\Business\TeamService;
use App\Support\ApiResponse;
use App\Support\Pagination;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

/**
 * The team of a business. Owners and managers may view it; only the owner
 * changes or removes members, and never the owner membership or their own.
 */
class BusinessMemberController extends Controller
{
    public function __construct(private readonly TeamService $team) {}

    /**
     * GET /businesses/{business}/members: owner first, then managers, then employees.
     */
    public function index(Request $request, Business $business): JsonResponse
    {
        Gate::authorize('viewMembers', $business);

        $paginator = $business->members()
            ->with(['user', 'branches'])
            ->orderByRaw("case role when 'owner' then 0 when 'manager' then 1 else 2 end")
            ->orderBy('id')
            ->paginate(Pagination::perPage($request));

        $paginator->getCollection()->each(fn (BusinessMember $member) => $member->setRelation('business', $business));

        return ApiResponse::paginated($paginator, BusinessMemberResource::class);
    }

    public function update(UpdateMemberRequest $request, Business $business, BusinessMember $member): JsonResponse
    {
        $this->team->update(
            $member,
            $request->user(),
            $request->role(),
            $request->branchAccess(),
            $request->assignedBranches(),
        );

        return ApiResponse::success(new BusinessMemberResource($member), __('api.member.updated'));
    }

    public function destroy(Request $request, Business $business, BusinessMember $member): JsonResponse
    {
        Gate::authorize('manageMembers', $business);

        $this->team->remove($member, $request->user());

        return ApiResponse::success(message: __('api.member.removed'));
    }
}

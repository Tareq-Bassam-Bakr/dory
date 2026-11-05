<?php

namespace App\Http\Controllers\Api\V1\Businesses;

use App\Enums\InvitationStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Members\StoreInvitationRequest;
use App\Http\Resources\BusinessInvitationResource;
use App\Models\Business;
use App\Models\BusinessInvitation;
use App\Services\Invitations\InvitationService;
use App\Support\ApiResponse;
use App\Support\Pagination;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Gate;

/**
 * Invitations of a business, managed by its owner (managers may view them).
 */
class BusinessInvitationController extends Controller
{
    public function __construct(private readonly InvitationService $invitations) {}

    /**
     * GET /businesses/{business}/invitations: open invitations (pending,
     * including ones that have just expired). ?status=all lists every
     * invitation.
     */
    public function index(Request $request, Business $business): JsonResponse
    {
        Gate::authorize('viewMembers', $business);
        $all = $request->query('status') === 'all';

        $paginator = $business->invitations()
            ->with('inviter')
            ->when(! $all, fn ($query) => $query->where('status', InvitationStatus::Pending->value))
            ->latest('id')
            ->paginate(Pagination::perPage($request));

        $this->attachBranches($business, $paginator->getCollection());

        return ApiResponse::paginated($paginator, BusinessInvitationResource::class);
    }

    /**
     * POST /businesses/{business}/invitations.
     *
     * The response reports honestly how the link was delivered. When share
     * links are enabled (development by default) it also returns the link
     * for manual sharing: the only time the token is ever available.
     */
    public function store(StoreInvitationRequest $request, Business $business): JsonResponse
    {
        $result = $this->invitations->invite(
            $business,
            $request->user(),
            $request->validated('email'),
            $request->role(),
            $request->access(),
            $request->assignedBranches(),
        );

        $this->attachBranches($business, collect([$result->invitation]));

        return ApiResponse::created([
            'invitation' => new BusinessInvitationResource($result->invitation),
            'delivery' => $result->delivery->toArray(),
            'share_url' => config('dory.invitations.share_links') ? $result->invitationUrl : null,
        ], __('api.invitation.created'));
    }

    /**
     * DELETE /businesses/{business}/invitations/{invitation}: revokes it.
     */
    public function destroy(Request $request, Business $business, BusinessInvitation $invitation): JsonResponse
    {
        Gate::authorize('manageMembers', $business);

        $this->invitations->revoke($invitation, $request->user());

        return ApiResponse::success(message: __('api.invitation.revoked'));
    }

    /**
     * Resolves branch_ids to branches of this business, in one query.
     *
     * @param  Collection<int, BusinessInvitation>  $invitations
     */
    private function attachBranches(Business $business, Collection $invitations): void
    {
        $ids = $invitations->flatMap(fn (BusinessInvitation $invitation) => $invitation->branch_ids ?? [])->unique();
        $branches = $ids->isEmpty() ? collect() : $business->branches()->whereIn('id', $ids)->get()->keyBy('id');

        $invitations->each(fn (BusinessInvitation $invitation) => $invitation->setRelation(
            'branches',
            collect($invitation->branch_ids ?? [])->map(fn (int $id) => $branches->get($id))->filter()->values(),
        ));
    }
}

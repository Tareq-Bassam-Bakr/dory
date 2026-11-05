<?php

namespace App\Http\Controllers\Api\V1\Businesses;

use App\Http\Controllers\Controller;
use App\Http\Resources\BusinessDetailResource;
use App\Http\Resources\InvitationPreviewResource;
use App\Models\BusinessInvitation;
use App\Services\Invitations\InvitationService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * The invitee's side of an invitation, addressed by its secret token.
 *
 * Viewing works without signing in (so the app can show what the link is
 * about); accepting and declining require the invited account.
 */
class InvitationController extends Controller
{
    public function __construct(private readonly InvitationService $invitations) {}

    public function show(Request $request, BusinessInvitation $invitationToken): JsonResponse
    {
        // Optional sign-in: identifies the viewer without requiring a token.
        $viewer = $request->user('sanctum');

        return ApiResponse::success(new InvitationPreviewResource($invitationToken, $viewer));
    }

    /**
     * Returns the business the user just joined.
     */
    public function accept(Request $request, BusinessInvitation $invitationToken): JsonResponse
    {
        $membership = $this->invitations->accept($invitationToken, $request->user());

        return ApiResponse::success(
            BusinessDetailResource::for($invitationToken->business, $membership),
            __('api.invitation.accepted'),
        );
    }

    public function decline(Request $request, BusinessInvitation $invitationToken): JsonResponse
    {
        $this->invitations->decline($invitationToken, $request->user());

        return ApiResponse::success(message: __('api.invitation.declined'));
    }
}

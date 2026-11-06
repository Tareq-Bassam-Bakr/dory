<?php

namespace App\Services\Business;

use App\Exceptions\BranchNotFoundException;
use App\Exceptions\BusinessNotFoundException;
use App\Exceptions\InvitationNotFoundException;
use App\Exceptions\MemberNotFoundException;
use App\Exceptions\QueueException;
use App\Exceptions\ServiceNotFoundException;
use App\Models\Branch;
use App\Models\Business;
use App\Models\BusinessInvitation;
use App\Models\BusinessMember;
use App\Models\Queue;
use App\Models\QueueSession;
use App\Models\QueueStaffAssignment;
use App\Models\QueueTicket;
use App\Models\Service;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;

/**
 * Resolves tenant-owned route parameters ({business}, {branch}, {service},
 * {member}, {invitation}, {invitationToken}). Registered as route bindings
 * in AppServiceProvider.
 *
 * Every lookup starts from the authenticated user's own active memberships
 * and branch access, so records of other tenants (or of branches the user
 * may not reach) are never loaded. Not found and not allowed produce the
 * same 404. On success the request's CurrentBusinessContext holds the
 * membership in the resource's business.
 */
final class TenantResolver
{
    public function __construct(
        private readonly CurrentBusinessContext $context,
        private readonly BusinessMembershipService $memberships,
    ) {}

    public function business(?User $user, string $publicId): Business
    {
        if ($user === null) {
            throw new BusinessNotFoundException;
        }

        return $this->context->resolveForUser($user, $publicId)->business;
    }

    public function branch(?User $user, string $publicId): Branch
    {
        $branch = $user === null ? null : Branch::query()
            ->where('public_id', $publicId)
            ->whereHas('business', fn (Builder $business) => $business->accessibleBy($user))
            ->with('business.category')
            ->first();

        $membership = $branch === null ? null : $this->memberships->findMembership($user, $branch->business);

        if ($membership === null || ! $membership->canAccessBranch($branch)) {
            throw new BranchNotFoundException;
        }

        $membership->setRelation('business', $branch->business);
        $this->context->use($membership);

        return $branch;
    }

    public function service(?User $user, string $publicId): Service
    {
        $service = $user === null ? null : Service::query()
            ->where('public_id', $publicId)
            ->whereHas('branch.business', fn (Builder $business) => $business->accessibleBy($user))
            ->with('branch.business')
            ->first();

        $branch = $service?->branch;
        $membership = $branch === null ? null : $this->memberships->findMembership($user, $branch->business);

        if ($membership === null || ! $membership->canAccessBranch($branch)) {
            throw new ServiceNotFoundException;
        }

        $membership->setRelation('business', $branch->business);
        $this->context->use($membership);

        return $service;
    }

    /**
     * A queue of a branch the user may reach.
     */
    public function queue(?User $user, string $publicId): Queue
    {
        $queue = $user === null ? null : Queue::query()
            ->where('public_id', $publicId)
            ->whereHas('branch.business', fn (Builder $business) => $business->accessibleBy($user))
            ->with(['branch.business', 'service'])
            ->first();

        $this->useBranchMembership($user, $queue?->branch) ?? throw QueueException::notFound('queue');

        return $queue;
    }

    /**
     * A session of a queue the user may reach.
     */
    public function queueSession(?User $user, string $publicId): QueueSession
    {
        $session = $user === null ? null : QueueSession::query()
            ->where('public_id', $publicId)
            ->whereHas('queue', fn (Builder $queue) => $queue->whereHas(
                'branch.business',
                fn (Builder $business) => $business->accessibleBy($user),
            ))
            ->with(['queue.branch.business', 'queue.service'])
            ->first();

        $this->useBranchMembership($user, $session?->queue?->branch) ?? throw QueueException::notFound('session');

        return $session;
    }

    /**
     * A ticket of a queue the user may reach (staff access).
     */
    public function staffTicket(?User $user, string $publicId): QueueTicket
    {
        $ticket = $user === null ? null : QueueTicket::query()
            ->where('public_id', $publicId)
            ->whereHas('queue', fn (Builder $queue) => $queue->whereHas(
                'branch.business',
                fn (Builder $business) => $business->accessibleBy($user),
            ))
            ->with(['queue.branch.business', 'queue.service', 'session'])
            ->first();

        $this->useBranchMembership($user, $ticket?->queue?->branch) ?? throw QueueException::notFound('ticket');

        return $ticket;
    }

    /**
     * A staff assignment of the queue already resolved for this request.
     */
    public function assignment(?Queue $queue, string $publicId): QueueStaffAssignment
    {
        return $queue?->staffAssignments()->where('public_id', $publicId)->with('member.user')->first()
            ?? throw QueueException::notFound('assignment');
    }

    /**
     * Sets the context from the user's membership when it reaches the
     * branch; null otherwise.
     */
    private function useBranchMembership(?User $user, ?Branch $branch): ?BusinessMember
    {
        if ($user === null || $branch === null || $branch->business === null) {
            return null;
        }

        $membership = $this->memberships->findMembership($user, $branch->business);
        if ($membership === null || ! $membership->canAccessBranch($branch)) {
            return null;
        }

        $membership->setRelation('business', $branch->business);

        return $this->context->use($membership);
    }

    /**
     * A membership of the business already resolved for this request.
     */
    public function member(string $publicId): BusinessMember
    {
        if (! $this->context->isResolved()) {
            throw new MemberNotFoundException;
        }

        $business = $this->context->business();
        $member = $business->members()
            ->where('public_id', $publicId)
            ->with(['user', 'branches'])
            ->first()
            ?? throw new MemberNotFoundException;

        return $member->setRelation('business', $business);
    }

    /**
     * An invitation of the business already resolved for this request.
     */
    public function invitation(string $publicId): BusinessInvitation
    {
        if (! $this->context->isResolved()) {
            throw new InvitationNotFoundException;
        }

        $business = $this->context->business();
        $invitation = $business->invitations()
            ->where('public_id', $publicId)
            ->with('inviter')
            ->first()
            ?? throw new InvitationNotFoundException;

        return $invitation->setRelation('business', $business);
    }

    /**
     * An invitation by its secret token (hashed before the lookup). The
     * business must still exist.
     */
    public function invitationByToken(string $token): BusinessInvitation
    {
        $invitation = BusinessInvitation::query()
            ->where('token_hash', BusinessInvitation::hashToken($token))
            ->whereHas('business')
            ->with(['business.category', 'inviter'])
            ->first();

        return $invitation ?? throw new InvitationNotFoundException;
    }
}

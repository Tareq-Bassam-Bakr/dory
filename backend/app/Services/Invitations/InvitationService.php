<?php

namespace App\Services\Invitations;

use App\Contracts\InvitationNotifier;
use App\Enums\AuditAction;
use App\Enums\BranchAccess;
use App\Enums\BusinessRole;
use App\Enums\InvitationStatus;
use App\Enums\MembershipStatus;
use App\Exceptions\DuplicateMembershipException;
use App\Exceptions\InvitationAlreadyPendingException;
use App\Exceptions\InvitationEmailMismatchException;
use App\Exceptions\InvitationExpiredException;
use App\Exceptions\InvitationNotPendingException;
use App\Models\Branch;
use App\Models\Business;
use App\Models\BusinessInvitation;
use App\Models\BusinessMember;
use App\Models\User;
use App\Services\Audit\AuditLogger;
use App\Services\Business\BusinessMembershipService;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Team invitations: create, revoke, accept, decline.
 *
 * - Tokens are 64 random characters from a CSPRNG; only their SHA-256 hash
 *   is stored. The plain token appears once, in the invitation link.
 * - One pending invitation per email per business (unique pending_key).
 * - Invitations expire, and each one can be used once: accepting,
 *   declining or revoking closes it for good.
 * - Only the account with the invited email can accept or decline.
 * - Invitations grant manager or employee roles only. Ownership is never
 *   transferable by invitation.
 */
final class InvitationService
{
    public const TOKEN_LENGTH = 64;

    public function __construct(
        private readonly BusinessMembershipService $memberships,
        private readonly InvitationNotifier $notifier,
        private readonly AuditLogger $audit,
    ) {}

    /**
     * @param  Collection<int, Branch>  $branches  Branches of this business for "selected" access.
     *
     * @throws DuplicateMembershipException When the email already belongs to a member.
     * @throws InvitationAlreadyPendingException When an open invitation for the email exists.
     */
    public function invite(
        Business $business,
        User $inviter,
        string $email,
        BusinessRole $role,
        BranchAccess $branchAccess,
        Collection $branches,
    ): InvitationResult {
        $email = mb_strtolower(trim($email));
        $this->ensureNotAMember($business, $email);
        $token = Str::random(self::TOKEN_LENGTH);

        try {
            $invitation = DB::transaction(function () use ($business, $inviter, $email, $role, $branchAccess, $branches, $token): BusinessInvitation {
                // Expired invitations no longer block a new one.
                $business->invitations()
                    ->where('email', $email)
                    ->where('status', InvitationStatus::Pending->value)
                    ->where('expires_at', '<=', now())
                    ->update(['status' => InvitationStatus::Expired->value, 'pending_key' => null]);

                $invitation = new BusinessInvitation([
                    'email' => $email,
                    'role' => $role,
                    'branch_access' => $branchAccess,
                    'branch_ids' => $branchAccess === BranchAccess::Selected
                        ? $branches->filter(fn (Branch $branch) => $branch->business_id === $business->getKey())
                            ->map(fn (Branch $branch) => $branch->getKey())->values()->all()
                        : null,
                    'expires_at' => now()->addDays(max(1, (int) config('dory.invitations.expires_in_days'))),
                ]);
                $invitation->token_hash = BusinessInvitation::hashToken($token);
                $invitation->pending_key = BusinessInvitation::pendingKeyFor($business, $email);
                $invitation->status = InvitationStatus::Pending;
                $invitation->business()->associate($business);
                $invitation->inviter()->associate($inviter);
                $invitation->saveOrFail();

                $this->audit->record($business, $inviter, AuditAction::InvitationCreated, $invitation, [
                    'invitation' => $invitation->public_id,
                    'email' => $email,
                    'role' => $role->value,
                    'branch_access' => $branchAccess->value,
                ]);

                return $invitation;
            });
        } catch (UniqueConstraintViolationException) {
            throw new InvitationAlreadyPendingException;
        }

        $invitation->setRelation('business', $business)->setRelation('inviter', $inviter);
        $url = self::urlFor($token);

        return new InvitationResult($invitation, $url, $this->notifier->deliver($invitation, $url));
    }

    /**
     * @throws InvitationNotPendingException When the invitation is already closed.
     */
    public function revoke(BusinessInvitation $invitation, User $actor): void
    {
        DB::transaction(function () use ($invitation, $actor): void {
            $locked = $this->lock($invitation);

            if ($locked->status !== InvitationStatus::Pending) {
                throw new InvitationNotPendingException;
            }

            $this->close($locked, InvitationStatus::Revoked);
            $this->audit->record($locked->business, $actor, AuditAction::InvitationRevoked, $locked, [
                'invitation' => $locked->public_id,
            ]);
        });
    }

    /**
     * Creates the membership and closes the invitation, atomically.
     *
     * @throws InvitationNotPendingException|InvitationExpiredException|InvitationEmailMismatchException|DuplicateMembershipException
     */
    public function accept(BusinessInvitation $invitation, User $user): BusinessMember
    {
        return DB::transaction(function () use ($invitation, $user): BusinessMember {
            $locked = $this->lock($invitation);
            $this->ensureCanRespond($locked, $user);

            $business = $locked->business;
            $branches = $locked->branch_access === BranchAccess::Selected
                ? $business->branches()->whereIn('id', $locked->branch_ids ?? [])->get()
                : null;

            $membership = $this->memberships->addMember(
                $business,
                $user,
                $locked->role,
                MembershipStatus::Active,
                $locked->branch_access,
                $branches,
            );

            $locked->accepted_by = $user->getKey();
            $this->close($locked, InvitationStatus::Accepted);

            $this->audit->record($business, $user, AuditAction::InvitationAccepted, $membership, [
                'invitation' => $locked->public_id,
                'role' => $locked->role->value,
            ]);

            return $membership;
        });
    }

    /**
     * @throws InvitationNotPendingException|InvitationExpiredException|InvitationEmailMismatchException
     */
    public function decline(BusinessInvitation $invitation, User $user): void
    {
        DB::transaction(function () use ($invitation, $user): void {
            $locked = $this->lock($invitation);
            $this->ensureCanRespond($locked, $user);

            $this->close($locked, InvitationStatus::Declined);
            $this->audit->record($locked->business, $user, AuditAction::InvitationDeclined, $locked, [
                'invitation' => $locked->public_id,
            ]);
        });
    }

    /**
     * Whether $user may accept or decline right now.
     */
    public static function canRespond(BusinessInvitation $invitation, ?User $user): bool
    {
        return $user !== null && $invitation->isOpen() && self::emailMatches($invitation, $user);
    }

    public static function urlFor(string $token): string
    {
        return str_replace('{token}', $token, (string) config('dory.invitations.url'));
    }

    private function ensureNotAMember(Business $business, string $email): void
    {
        $user = User::query()->where('email', $email)->first();

        if ($user !== null && $this->memberships->findMembership($user, $business, activeOnly: false) !== null) {
            throw new DuplicateMembershipException;
        }
    }

    private function ensureCanRespond(BusinessInvitation $invitation, User $user): void
    {
        if ($invitation->status !== InvitationStatus::Pending) {
            throw new InvitationNotPendingException;
        }

        if ($invitation->isExpired()) {
            throw new InvitationExpiredException;
        }

        if (! self::emailMatches($invitation, $user)) {
            throw new InvitationEmailMismatchException;
        }
    }

    private static function emailMatches(BusinessInvitation $invitation, User $user): bool
    {
        return hash_equals($invitation->email, mb_strtolower($user->email));
    }

    /**
     * Re-reads the invitation with a row lock, so two concurrent requests
     * cannot both use it.
     */
    private function lock(BusinessInvitation $invitation): BusinessInvitation
    {
        $locked = BusinessInvitation::query()->whereKey($invitation->getKey())->lockForUpdate()->firstOrFail();

        return $locked->setRelation('business', $invitation->business);
    }

    private function close(BusinessInvitation $invitation, InvitationStatus $status): void
    {
        $invitation->status = $status;
        $invitation->pending_key = null;
        $invitation->responded_at = now();
        $invitation->saveOrFail();
    }
}

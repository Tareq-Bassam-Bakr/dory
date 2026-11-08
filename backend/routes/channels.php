<?php

use App\Enums\BusinessRole;
use App\Models\Business;
use App\Models\QueueSession;
use App\Models\QueueTicket;
use App\Models\User;
use App\Services\Business\BusinessAbilities;
use App\Services\Business\CurrentBusinessContext;
use Illuminate\Support\Facades\Broadcast;

/*
 * Private channel authorization (docs/BROADCAST_CHANNELS.md).
 *
 * Reached through POST /api/v1/broadcasting/auth with the user's Sanctum
 * token. Each rule checks the authenticated user against the database: the
 * channel name the app asks for is never trusted on its own. A refusal looks
 * the same whether the record exists or not.
 *
 * The public guest channel, queue-updates.{session}, needs no rule.
 */

// The user's own channel: notifications.
Broadcast::channel('users.{userId}', fn (User $user, string $userId): bool => $user->public_id === $userId);

// A signed-in customer's own ticket.
Broadcast::channel('tickets.{ticketId}', fn (User $user, string $ticketId): bool => QueueTicket::query()
    ->where('public_id', $ticketId)
    ->where('user_id', $user->getKey())
    ->exists());

// A queue session, for the staff who run that queue: managers who reach its
// branch and employees assigned to the queue.
Broadcast::channel('queue-sessions.{sessionId}', function (User $user, string $sessionId): bool {
    $session = QueueSession::query()->with('queue.branch.business')->where('public_id', $sessionId)->first();
    $queue = $session?->queue;
    if ($queue === null) {
        return false;
    }

    $business = $queue->branch->business;
    $abilities = BusinessAbilities::for($business, app(CurrentBusinessContext::class)->membershipFor($user, $business));

    return $abilities->canViewBranch($queue->branch)
        && ($abilities->canManageQueues($queue->branch) || $abilities->isAssignedTo($queue));
});

// Every queue of a business: the owner and managers who reach all branches.
Broadcast::channel('businesses.{businessId}', function (User $user, string $businessId): bool {
    $business = Business::query()->where('public_id', $businessId)->first();
    if ($business === null) {
        return false;
    }

    $membership = app(CurrentBusinessContext::class)->membershipFor($user, $business);

    return $membership !== null
        && $membership->grantsAccess()
        && in_array($membership->role, [BusinessRole::Owner, BusinessRole::Manager], true)
        && $membership->hasAllBranchAccess();
});

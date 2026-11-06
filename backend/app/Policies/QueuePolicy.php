<?php

namespace App\Policies;

use App\Models\Queue;
use App\Models\User;
use App\Services\Business\BusinessAbilities;
use App\Services\Business\CurrentBusinessContext;
use Closure;
use Illuminate\Auth\Access\Response;

/**
 * Authorization for queues, their sessions and tickets (staff side).
 *
 * - view:    members who reach the queue's branch.
 * - manage:  configure, delete, assign staff, rotate the public link
 *            (owner, or manager with access to the branch).
 * - operate: open/pause/resume/close sessions, call and serve customers
 *            (managers as above, plus employees assigned to the queue).
 *
 * Users who cannot reach the queue are told it does not exist.
 */
class QueuePolicy
{
    public function __construct(private readonly CurrentBusinessContext $context) {}

    public function view(User $user, Queue $queue): Response
    {
        return $this->abilities($user, $queue)->canViewBranch($queue->branch)
            ? Response::allow()
            : self::notFound();
    }

    public function manage(User $user, Queue $queue): Response
    {
        return $this->decide($user, $queue, fn (BusinessAbilities $a) => $a->canManageQueues($queue->branch));
    }

    public function operate(User $user, Queue $queue): Response
    {
        return $this->decide($user, $queue, fn (BusinessAbilities $a) => $a->canOperateQueue($queue));
    }

    public static function notFound(): Response
    {
        return Response::denyAsNotFound(__('api.queue.queue_not_found'), 'queue_not_found');
    }

    private function abilities(User $user, Queue $queue): BusinessAbilities
    {
        $business = $queue->branch->business;

        return BusinessAbilities::for($business, $this->context->membershipFor($user, $business));
    }

    /**
     * @param  Closure(BusinessAbilities): bool  $check
     */
    private function decide(User $user, Queue $queue, Closure $check): Response
    {
        $abilities = $this->abilities($user, $queue);

        if (! $abilities->canViewBranch($queue->branch)) {
            return self::notFound();
        }

        if (! $abilities->acceptsChanges()) {
            return BusinessPolicy::suspended();
        }

        return $check($abilities) ? Response::allow() : Response::deny();
    }
}

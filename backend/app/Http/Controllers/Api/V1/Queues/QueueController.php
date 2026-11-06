<?php

namespace App\Http\Controllers\Api\V1\Queues;

use App\Http\Controllers\Controller;
use App\Http\Requests\Queues\StoreQueueRequest;
use App\Http\Requests\Queues\UpdateQueueRequest;
use App\Http\Resources\QueueResource;
use App\Models\Branch;
use App\Models\Business;
use App\Models\Queue;
use App\Services\Business\CurrentBusinessContext;
use App\Services\Queues\QueueService;
use App\Support\ApiResponse;
use App\Support\Pagination;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

/**
 * Queue configurations. {branch}, {business} and {queue} are resolved
 * through the user's memberships and branch access (TenantResolver).
 */
class QueueController extends Controller
{
    public function __construct(
        private readonly QueueService $queues,
        private readonly CurrentBusinessContext $context,
    ) {}

    /**
     * GET /branches/{branch}/queues
     */
    public function index(Request $request, Branch $branch): JsonResponse
    {
        Gate::authorize('view', $branch);

        $paginator = $branch->queues()
            ->with(['service', 'activeSession'])
            ->orderBy('name')
            ->orderBy('id')
            ->paginate(Pagination::perPage($request));

        $paginator->getCollection()->each(fn (Queue $queue) => $queue->setRelation('branch', $branch));

        return ApiResponse::paginated($paginator, QueueResource::class);
    }

    /**
     * GET /businesses/{business}/queues: every queue the user may reach in
     * the business (all branches, or the assigned ones).
     */
    public function businessIndex(Request $request, Business $business): JsonResponse
    {
        Gate::authorize('view', $business);
        $membership = $this->context->membership();

        $paginator = Queue::query()
            ->whereHas('branch', fn ($branches) => $branches->where('business_id', $business->getKey()))
            ->when(! $membership->hasAllBranchAccess(), fn ($query) => $query
                ->whereIn('branch_id', $membership->branches()->pluck('branches.id')))
            ->with(['branch', 'service', 'activeSession'])
            ->orderBy('name')
            ->orderBy('id')
            ->paginate(Pagination::perPage($request));

        $paginator->getCollection()->each(fn (Queue $queue) => $queue->branch->setRelation('business', $business));

        return ApiResponse::paginated($paginator, QueueResource::class);
    }

    public function store(StoreQueueRequest $request, Branch $branch): JsonResponse
    {
        $queue = $this->queues->create($branch, $request->service(), $request->configuration());

        return ApiResponse::created($this->present($queue), __('api.queue.created'));
    }

    public function show(Queue $queue): JsonResponse
    {
        Gate::authorize('view', $queue);

        return ApiResponse::success($this->present($queue));
    }

    public function update(UpdateQueueRequest $request, Queue $queue): JsonResponse
    {
        $this->queues->update($queue, $request->validated());

        return ApiResponse::success($this->present($queue), __('api.queue.updated'));
    }

    /**
     * DELETE /queues/{queue}: soft delete; refused while a session is active.
     */
    public function destroy(Queue $queue): JsonResponse
    {
        Gate::authorize('manage', $queue);

        $this->queues->delete($queue);

        return ApiResponse::success(message: __('api.queue.deleted'));
    }

    /**
     * GET /queues/{queue}/public-link: the join URL and QR payload.
     */
    public function publicLink(Queue $queue): JsonResponse
    {
        Gate::authorize('manage', $queue);

        return ApiResponse::success($this->linkPayload($queue));
    }

    /**
     * POST /queues/{queue}/rotate-public-token: the old link stops working.
     */
    public function rotatePublicToken(Queue $queue): JsonResponse
    {
        Gate::authorize('manage', $queue);

        $this->queues->rotatePublicToken($queue);

        return ApiResponse::success($this->linkPayload($queue), __('api.queue.token_rotated'));
    }

    /**
     * @return array<string, mixed>
     */
    private function linkPayload(Queue $queue): array
    {
        $url = $this->queues->publicLink($queue);

        return [
            'url' => $url,
            // The QR code encodes exactly the URL.
            'qr_payload' => $url,
            'rotated_at' => $queue->public_token_rotated_at?->toIso8601String(),
            'allow_guest_join' => $queue->allow_guest_join,
        ];
    }

    private function present(Queue $queue): QueueResource
    {
        $queue->loadMissing(['branch', 'service'])->load('activeSession');

        return new QueueResource($queue);
    }
}

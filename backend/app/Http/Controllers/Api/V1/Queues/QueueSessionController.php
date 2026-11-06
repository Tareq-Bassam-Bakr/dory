<?php

namespace App\Http\Controllers\Api\V1\Queues;

use App\Http\Controllers\Controller;
use App\Http\Resources\QueueSessionResource;
use App\Models\Queue;
use App\Services\Queues\QueueSessionService;
use App\Support\ApiResponse;
use App\Support\Pagination;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

/**
 * Opening, pausing, resuming and closing a queue, and its session history.
 */
class QueueSessionController extends Controller
{
    public function __construct(private readonly QueueSessionService $sessions) {}

    public function open(Request $request, Queue $queue): JsonResponse
    {
        Gate::authorize('operate', $queue);

        return ApiResponse::created(
            new QueueSessionResource($this->sessions->open($queue, $request->user())),
            __('api.queue.opened'),
        );
    }

    public function pause(Request $request, Queue $queue): JsonResponse
    {
        Gate::authorize('operate', $queue);

        return ApiResponse::success(
            new QueueSessionResource($this->sessions->pause($queue, $request->user())),
            __('api.queue.paused'),
        );
    }

    public function resume(Request $request, Queue $queue): JsonResponse
    {
        Gate::authorize('operate', $queue);

        return ApiResponse::success(
            new QueueSessionResource($this->sessions->resume($queue, $request->user())),
            __('api.queue.resumed'),
        );
    }

    public function close(Request $request, Queue $queue): JsonResponse
    {
        Gate::authorize('operate', $queue);

        return ApiResponse::success(
            new QueueSessionResource($this->sessions->close($queue, $request->user())),
            __('api.queue.closed'),
        );
    }

    /**
     * GET /queues/{queue}/current-session: the open or paused session, or null.
     */
    public function current(Queue $queue): JsonResponse
    {
        Gate::authorize('view', $queue);
        $session = $this->sessions->current($queue);

        return ApiResponse::success($session === null ? null : new QueueSessionResource($session));
    }

    /**
     * GET /queues/{queue}/sessions: newest first, with ticket counts.
     */
    public function index(Request $request, Queue $queue): JsonResponse
    {
        Gate::authorize('view', $queue);

        $paginator = $queue->sessions()
            ->withCount('tickets')
            ->orderByDesc('id')
            ->paginate(Pagination::perPage($request));

        return ApiResponse::paginated($paginator, QueueSessionResource::class);
    }
}

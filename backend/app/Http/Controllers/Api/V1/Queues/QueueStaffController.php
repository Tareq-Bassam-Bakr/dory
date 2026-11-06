<?php

namespace App\Http\Controllers\Api\V1\Queues;

use App\Http\Controllers\Controller;
use App\Http\Requests\Queues\AssignQueueStaffRequest;
use App\Http\Resources\QueueStaffAssignmentResource;
use App\Models\Queue;
use App\Models\QueueStaffAssignment;
use App\Services\Queues\QueueStaffService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

/**
 * Employees assigned to operate a queue.
 */
class QueueStaffController extends Controller
{
    public function __construct(private readonly QueueStaffService $staff) {}

    /**
     * GET /queues/{queue}/staff: current assignments.
     */
    public function index(Queue $queue): JsonResponse
    {
        Gate::authorize('view', $queue);

        $assignments = $queue->activeStaffAssignments()
            ->with(['member.user', 'assigner'])
            ->orderBy('assigned_at')
            ->orderBy('id')
            ->get();

        return ApiResponse::success(QueueStaffAssignmentResource::collection($assignments));
    }

    public function store(AssignQueueStaffRequest $request, Queue $queue): JsonResponse
    {
        [$assignment, $created] = $this->staff->assign($queue, $request->member(), $request->user());
        $resource = new QueueStaffAssignmentResource($assignment->loadMissing(['member.user', 'assigner']));

        return $created
            ? ApiResponse::created($resource, __('api.queue.staff_assigned'))
            : ApiResponse::success($resource, __('api.queue.staff_assigned'));
    }

    public function destroy(Request $request, Queue $queue, QueueStaffAssignment $assignment): JsonResponse
    {
        Gate::authorize('manage', $queue);

        $this->staff->unassign($assignment, $request->user());

        return ApiResponse::success(message: __('api.queue.staff_unassigned'));
    }
}

<?php

namespace App\Http\Controllers\Api\V1\Queues;

use App\Http\Controllers\Controller;
use App\Http\Resources\StaffTicketResource;
use App\Models\QueueSession;
use App\Models\QueueTicket;
use App\Services\Queues\QueueOperationsService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

/**
 * Serving customers. Operating requires the "operate" permission on the
 * queue (managers of the branch, or assigned employees); calling a
 * specific ticket out of order requires "manage".
 */
class QueueOperationsController extends Controller
{
    public function __construct(private readonly QueueOperationsService $operations) {}

    /**
     * POST /queue-sessions/{session}/call-next
     */
    public function callNext(Request $request, QueueSession $queueSession): JsonResponse
    {
        Gate::authorize('operate', $queueSession->queue);

        return $this->respond($this->operations->callNext($queueSession, $request->user()));
    }

    /**
     * GET /tickets/{ticket}: details and history. Operators see the guest's
     * phone number in full; other team members see it masked.
     */
    public function show(QueueTicket $ticket): JsonResponse
    {
        Gate::authorize('view', $ticket->queue);

        $ticket->load(['user', 'server', 'events' => fn ($events) => $events->with('actor')->orderBy('id')]);

        return ApiResponse::success((new StaffTicketResource($ticket))
            ->withContactDetails(Gate::allows('operate', $ticket->queue))
            ->withTimeline());
    }

    public function call(Request $request, QueueTicket $ticket): JsonResponse
    {
        Gate::authorize('manage', $ticket->queue);

        return $this->respond($this->operations->call($ticket, $request->user()));
    }

    public function start(Request $request, QueueTicket $ticket): JsonResponse
    {
        Gate::authorize('operate', $ticket->queue);

        return $this->respond($this->operations->start($ticket, $request->user(), $this->isManager($ticket)));
    }

    public function complete(Request $request, QueueTicket $ticket): JsonResponse
    {
        Gate::authorize('operate', $ticket->queue);

        return $this->respond($this->operations->complete($ticket, $request->user(), $this->isManager($ticket)));
    }

    public function skip(Request $request, QueueTicket $ticket): JsonResponse
    {
        Gate::authorize('operate', $ticket->queue);

        return $this->respond($this->operations->skip($ticket, $request->user()));
    }

    public function noShow(Request $request, QueueTicket $ticket): JsonResponse
    {
        Gate::authorize('operate', $ticket->queue);

        return $this->respond($this->operations->noShow($ticket, $request->user(), $this->isManager($ticket)));
    }

    public function recall(Request $request, QueueTicket $ticket): JsonResponse
    {
        Gate::authorize('operate', $ticket->queue);

        return $this->respond($this->operations->recall($ticket, $request->user(), $this->isManager($ticket)));
    }

    private function isManager(QueueTicket $ticket): bool
    {
        return Gate::allows('manage', $ticket->queue);
    }

    private function respond(QueueTicket $ticket): JsonResponse
    {
        $ticket->load(['user', 'server']);

        return ApiResponse::success(new StaffTicketResource($ticket), __('api.queue.ticket_updated'));
    }
}

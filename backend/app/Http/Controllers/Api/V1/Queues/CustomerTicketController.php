<?php

namespace App\Http\Controllers\Api\V1\Queues;

use App\Enums\TicketStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Queues\CustomerTicketsRequest;
use App\Http\Requests\Queues\JoinQueueRequest;
use App\Http\Requests\Queues\ReviewTicketRequest;
use App\Http\Resources\CustomerTicketResource;
use App\Models\Queue;
use App\Models\QueueTicket;
use App\Services\Queues\TicketCancellationService;
use App\Services\Queues\TicketJoinService;
use App\Services\Queues\TicketReviewService;
use App\Support\ApiResponse;
use App\Support\Pagination;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Signed-in customers: join a queue, follow and cancel their own tickets.
 * {joinableQueue} is any live queue; {myTicket} only ever resolves to a
 * ticket of the signed-in user (PublicQueueResolver).
 */
class CustomerTicketController extends Controller
{
    public function __construct(
        private readonly TicketJoinService $joins,
        private readonly TicketCancellationService $cancellations,
        private readonly TicketReviewService $reviews,
    ) {}

    public function join(JoinQueueRequest $request, Queue $joinableQueue): JsonResponse
    {
        $result = $this->joins->joinAsCustomer($joinableQueue, $request->user(), $request->idempotencyKey());
        $resource = new CustomerTicketResource($this->loadTicket($result->ticket));

        return $result->created
            ? ApiResponse::created($resource, __('api.queue.joined'))
            : ApiResponse::success($resource, __('api.queue.already_joined'));
    }

    /**
     * GET /me/tickets: active tickets first, then the most recent ones.
     * Optional filters: ?group=active|history and ?status=waiting,called.
     */
    public function index(CustomerTicketsRequest $request): JsonResponse
    {
        $active = TicketStatus::activeValues();
        $placeholders = implode(', ', array_fill(0, count($active), '?'));

        $paginator = QueueTicket::query()
            ->where('user_id', $request->user()->getKey())
            ->when($request->statuses() !== null, fn ($query) => $query->whereIn('status', $request->statuses()))
            ->with(['queue.branch.business', 'queue.service', 'session', 'review'])
            ->orderByRaw("case when status in ({$placeholders}) then 0 else 1 end", $active)
            ->orderByDesc('id')
            ->paginate(Pagination::perPage($request));

        return ApiResponse::paginated($paginator, CustomerTicketResource::class);
    }

    /**
     * GET /tickets/{ticket}/position
     */
    public function position(QueueTicket $myTicket): JsonResponse
    {
        return ApiResponse::success(new CustomerTicketResource($myTicket));
    }

    public function cancel(Request $request, QueueTicket $myTicket): JsonResponse
    {
        $ticket = $this->cancellations->cancelByHolder($myTicket, $request->user());

        return ApiResponse::success(new CustomerTicketResource($this->loadTicket($ticket)), __('api.queue.ticket_cancelled'));
    }

    /**
     * POST /tickets/{ticket}/review: one rating for a ticket the customer
     * completed.
     */
    public function review(ReviewTicketRequest $request, QueueTicket $myTicket): JsonResponse
    {
        $this->reviews->submit($myTicket, $request->user(), $request->integer('rating'));

        return ApiResponse::created(
            new CustomerTicketResource($this->loadTicket($myTicket)->unsetRelation('review')),
            __('api.queue.review_saved'),
        );
    }

    private function loadTicket(QueueTicket $ticket): QueueTicket
    {
        return $ticket->loadMissing(['queue.branch.business', 'queue.service', 'session']);
    }
}
